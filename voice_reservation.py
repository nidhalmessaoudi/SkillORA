"""
voice_server_improved.py
========================
Améliorations vs version originale :
  - Whisper (local, openai-whisper) pour la transcription → plus de latence API audio,
    meilleure précision noms propres, bilingue fr/en natif.
  - Gemini utilisé UNIQUEMENT sur le texte transcrit (pas l'audio) → ~10x moins cher.
  - Normalisation robuste des numéros de téléphone via la lib `phonenumbers`.
  - Détection automatique de la langue (fr / en) pour adapter les messages TTS.
  - Pool de connexions DB (mysql-connector pooling) pour éviter l'overhead de reconnexion.
  - Nettoyage automatique des fichiers audio après traitement.

Dépendances à installer :
    pip install openai-whisper phonenumbers google-generativeai mysql-connector-python
    pip install pygame gtts sounddevice soundfile flask requests
    # Pour GPU (optionnel mais recommandé) :
    pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118
"""

import os
import re
import time
import threading
from io import BytesIO

import whisper
import phonenumbers
import google.generativeai as genai
import mysql.connector
from mysql.connector import pooling
import pygame
from gtts import gTTS
from flask import Flask, jsonify
import sounddevice as sd
import soundfile as sf
import tkinter as tk
from tkinter import messagebox
import requests

# ─────────────────────────────────────────────
# Configuration
# ─────────────────────────────────────────────
GEMINI_API_KEY = os.environ.get('GEMINI_API_KEY', 'VOTRE_CLE_ICI')
DATABASE_URL   = os.environ.get(
    'DATABASE_URL',
    "mysql://root:@127.0.0.1:3306/skillora?serverVersion=8.0.32&charset=utf8mb4"
)

# Taille du modèle Whisper : "tiny" | "base" | "small" | "medium" | "large"
# Recommandation : "small" → bon équilibre vitesse/précision sans GPU
#                  "medium" → meilleure précision noms propres, nécessite ~4 Go RAM
WHISPER_MODEL_SIZE = os.environ.get('WHISPER_MODEL', 'small')

# Pays par défaut pour la normalisation téléphone (ISO 3166-1 alpha-2)
DEFAULT_PHONE_REGION = 'TN'   # Tunisie — changer selon votre contexte

# ─────────────────────────────────────────────
# Parsing DATABASE_URL
# ─────────────────────────────────────────────
def parse_database_url(url: str) -> dict:
    m = re.match(
        r"mysql://(?P<user>[^:@]+):?(?P<pass>[^@]*)@(?P<host>[^:/]+)(:(?P<port>\d+))?/(?P<db>[^?]+)",
        url
    )
    if not m:
        return {'host': '127.0.0.1', 'database': 'skillora', 'user': 'root', 'password': '', 'port': 3306}
    gd = m.groupdict()
    return {
        'host':     gd.get('host') or '127.0.0.1',
        'database': gd.get('db'),
        'user':     gd.get('user'),
        'password': gd.get('pass') or '',
        'port':     int(gd.get('port') or 3306),
    }

DB_CONFIG = parse_database_url(DATABASE_URL)

# ─────────────────────────────────────────────
# Pool de connexions MySQL
# ─────────────────────────────────────────────
_db_pool = pooling.MySQLConnectionPool(
    pool_name="skillora_pool",
    pool_size=5,
    **DB_CONFIG
)

def get_db_connection():
    return _db_pool.get_connection()

# ─────────────────────────────────────────────
# Audio / enregistrement
# ─────────────────────────────────────────────
SAMPLE_RATE = 16000   # Whisper préfère 16 kHz (down-sample automatique sinon)
AUDIO_DIR   = os.path.join(os.path.dirname(__file__), 'audio_recordings')
os.makedirs(AUDIO_DIR, exist_ok=True)

recording_lock = threading.Lock()
_rec = {'stream': None, 'frames': [], 'mode': None}

def start_recording(mode: str = 'add'):
    with recording_lock:
        if _rec['stream'] is not None:
            return False, 'Already recording'
        _rec['frames'] = []
        _rec['mode']   = mode

        def callback(indata, frames, time_info, status):
            if status:
                print('[Audio]', status)
            _rec['frames'].append(indata.copy())

        stream = sd.InputStream(samplerate=SAMPLE_RATE, channels=1, dtype='float32', callback=callback)
        stream.start()
        _rec['stream'] = stream
        return True, 'Recording started'

def stop_recording_and_save():
    with recording_lock:
        stream = _rec.get('stream')
        mode   = _rec.get('mode')
        if stream is None:
            return None, None
        stream.stop()
        stream.close()
        frames = list(_rec.get('frames', []))
        _rec['stream'] = None
        _rec['frames'] = []
        _rec['mode']   = None

    if not frames:
        return None, None

    import numpy as np
    data     = np.concatenate(frames, axis=0)
    filename = os.path.join(AUDIO_DIR, f"rec_{int(time.time())}.wav")
    sf.write(filename, data, SAMPLE_RATE)
    return filename, mode

# ─────────────────────────────────────────────
# Initialisation des APIs
# ─────────────────────────────────────────────
genai.configure(api_key=GEMINI_API_KEY)
pygame.mixer.init(frequency=22050, buffer=512)

print(f"[Whisper] Chargement du modèle '{WHISPER_MODEL_SIZE}'…")
_whisper_model = whisper.load_model(WHISPER_MODEL_SIZE)
print("[Whisper] Modèle prêt.")

app = Flask(__name__)

# ─────────────────────────────────────────────
# Détection de langue simple
# ─────────────────────────────────────────────
_FR_WORDS = {'bonjour', 'je', 'me', 'mon', 'ma', 'prénom', 'nom', 'appelle',
             'numéro', 'téléphone', 'merci', 'voici', 'est', 'de', 'le', 'la'}

def detect_lang(text: str) -> str:
    """Retourne 'fr' ou 'en' selon les mots détectés dans le texte."""
    words = set(text.lower().split())
    return 'fr' if words & _FR_WORDS else 'en'

# ─────────────────────────────────────────────
# Text-to-Speech (gTTS + pygame)
# ─────────────────────────────────────────────
_tts_lock = threading.Lock()

def text_to_speech(text: str, lang: str = 'fr'):
    """Joue un message vocal. Thread-safe via lock."""
    with _tts_lock:
        try:
            fp = BytesIO()
            gTTS(text=text, lang=lang).write_to_fp(fp)
            fp.seek(0)
            pygame.mixer.music.load(fp)
            pygame.mixer.music.play()
            while pygame.mixer.music.get_busy():
                time.sleep(0.05)
        except Exception as e:
            print(f'[TTS] Erreur: {e}')

def speak_async(text: str, lang: str = 'fr'):
    threading.Thread(target=text_to_speech, args=(text, lang), daemon=True).start()

# ─────────────────────────────────────────────
# Normalisation du numéro de téléphone
# ─────────────────────────────────────────────
def normalize_phone(raw: str, region: str = DEFAULT_PHONE_REGION) -> str:
    """
    Tente de parser et normaliser le numéro avec la lib phonenumbers.
    Retourne le numéro au format E.164 (+21612345678) ou le numéro brut nettoyé.
    """
    # Nettoyer : garder chiffres, +, espaces
    cleaned = re.sub(r'[^\d\s\+\-\(\)]', '', raw).strip()

    try:
        parsed = phonenumbers.parse(cleaned, region)
        if phonenumbers.is_valid_number(parsed):
            return phonenumbers.format_number(parsed, phonenumbers.PhoneNumberFormat.E164)
    except Exception:
        pass

    # Fallback : juste les chiffres
    digits = re.sub(r'\D', '', cleaned)
    return digits if len(digits) >= 8 else ''

# ─────────────────────────────────────────────
# Transcription Whisper (locale)
# ─────────────────────────────────────────────
def transcribe_audio(audio_path: str) -> tuple[str, str]:
    """
    Transcrit l'audio avec Whisper.
    Retourne (texte_transcrit, langue_detectee).
    Whisper détecte automatiquement la langue (fr/en/ar/…).
    """
    result = _whisper_model.transcribe(
        audio_path,
        language=None,          # détection automatique
        task='transcribe',
        fp16=False,             # mettre True si GPU disponible
        condition_on_previous_text=False,
    )
    text = result.get('text', '').strip()
    lang = result.get('language', 'fr')   # 'fr', 'en', etc.
    print(f"[Whisper] Transcription ({lang}): {text}")
    return text, lang

# ─────────────────────────────────────────────
# Extraction structurée via Gemini (texte → JSON)
# ─────────────────────────────────────────────
_gemini_model = genai.GenerativeModel(model_name='gemini-2.0-flash')

def extract_reservation_fields(transcribed_text: str) -> dict:
    """
    Envoie le texte transcrit à Gemini pour extraire prénom, nom, téléphone.
    Beaucoup moins coûteux qu'envoyer l'audio brut.
    """
    prompt = f"""
Tu es un assistant d'extraction de données. 
Analyse le texte suivant (peut être en français ou en anglais) et extrait exactement :
- FIRST_NAME : le prénom
- LAST_NAME  : le nom de famille
- PHONE      : le numéro de téléphone (chiffres bruts, sans mise en forme)

Réponds UNIQUEMENT dans ce format strict, sans rien d'autre :
FIRST_NAME: <valeur>
LAST_NAME: <valeur>
PHONE: <valeur>

Si un champ est absent ou introuvable, laisse la valeur vide.

Texte à analyser :
\"\"\"{transcribed_text}\"\"\"
"""
    response = _gemini_model.generate_content(prompt)
    text     = getattr(response, 'text', '')
    print(f"[Gemini] Extraction: {text}")

    first = re.search(r'FIRST_NAME:\s*(.*)', text, re.I)
    last  = re.search(r'LAST_NAME:\s*(.*)',  text, re.I)
    phone = re.search(r'PHONE:\s*(.*)',       text, re.I)

    return {
        'prenom':    (first.group(1).strip() if first else ''),
        'nom':       (last.group(1).strip()  if last  else ''),
        'telephone': (phone.group(1).strip() if phone else ''),
    }

def extract_id_field(transcribed_text: str) -> int | None:
    """Extrait un ID numérique depuis le texte transcrit."""
    prompt = f"""
Extrait l'identifiant numérique de réservation dans ce texte.
Réponds UNIQUEMENT avec : ID: <nombre>
Si aucun ID trouvé, réponds : ID: 

Texte : \"\"\"{transcribed_text}\"\"\"
"""
    response = _gemini_model.generate_content(prompt)
    text     = getattr(response, 'text', '')
    m = re.search(r'ID\s*[:=]\s*(\d+)', text)
    return int(m.group(1)) if m else None

# ─────────────────────────────────────────────
# Base de données
# ─────────────────────────────────────────────
def execute_insert(prenom: str, nom: str, telephone: str) -> int | None:
    try:
        conn   = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(
            "INSERT INTO confirm_reservation (prenom, nom, telephone) VALUES (%s, %s, %s)",
            (prenom, nom, telephone)
        )
        conn.commit()
        rowid = cursor.lastrowid
        cursor.close()
        conn.close()
        return rowid
    except Exception as e:
        print(f'[DB] Insert error: {e}')
        return None

def execute_delete_id(idconfirm: int) -> int | None:
    try:
        conn   = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM confirm_reservation WHERE idconfirm = %s", (idconfirm,))
        affected = cursor.rowcount
        conn.commit()
        cursor.close()
        conn.close()
        return affected
    except Exception as e:
        print(f'[DB] Delete error: {e}')
        return None

# ─────────────────────────────────────────────
# Traitement des commandes vocales
# ─────────────────────────────────────────────
def process_add_command(audio_path: str) -> bool:
    try:
        # Étape 1 : Transcription locale (Whisper) — rapide, précis, gratuit
        transcribed, whisper_lang = transcribe_audio(audio_path)

        if not transcribed:
            lang = 'fr'
            speak_async("Aucune parole détectée. Veuillez réessayer.", lang)
            return False

        # Étape 2 : Extraction structurée (Gemini sur texte) — 1 seul appel léger
        fields = extract_reservation_fields(transcribed)

        prenom_val = fields['prenom']
        nom_val    = fields['nom']
        phone_raw  = fields['telephone']

        # Étape 3 : Normalisation du numéro
        phone_val = normalize_phone(phone_raw) if phone_raw else ''

        # Détection langue pour les messages vocaux
        lang = 'fr' if whisper_lang in ('fr', 'french') else 'en'

        # Validation
        missing = []
        if not prenom_val: missing.append('prénom' if lang == 'fr' else 'first name')
        if not nom_val:    missing.append('nom'    if lang == 'fr' else 'last name')
        if not phone_val:  missing.append('téléphone' if lang == 'fr' else 'phone number')

        if missing:
            champs = ', '.join(missing)
            msg = (f"Champ(s) manquant(s) : {champs}. Veuillez réessayer."
                   if lang == 'fr' else
                   f"Missing field(s): {champs}. Please try again.")
            speak_async(msg, lang)
            return False

        # Étape 4 : Insertion DB
        rowid = execute_insert(prenom_val, nom_val, phone_val)
        if rowid:
            msg = (f"Réservation ajoutée. {prenom_val} {nom_val}, téléphone {phone_val}."
                   if lang == 'fr' else
                   f"Reservation added. {prenom_val} {nom_val}, phone {phone_val}.")
            print(f"[Add] {msg}")
            speak_async(msg, lang)
            return True
        else:
            msg = ("Erreur lors de l'enregistrement en base de données."
                   if lang == 'fr' else
                   "Database error while saving reservation.")
            speak_async(msg, lang)
            return False

    except Exception as e:
        print(f'[Add] Erreur: {e}')
        speak_async("Erreur lors du traitement audio.", 'fr')
        return False
    finally:
        # Nettoyage du fichier audio temporaire
        try:
            os.remove(audio_path)
        except Exception:
            pass

def process_delete_command(audio_path: str) -> bool:
    try:
        # Étape 1 : Transcription Whisper
        transcribed, whisper_lang = transcribe_audio(audio_path)
        lang = 'fr' if whisper_lang in ('fr', 'french') else 'en'

        if not transcribed:
            speak_async("Aucune parole détectée.", lang)
            return False

        # Étape 2 : Extraction ID via Gemini (texte seulement)
        idval = extract_id_field(transcribed)

        if idval is None:
            msg = ("Identifiant non trouvé. Veuillez réessayer."
                   if lang == 'fr' else
                   "Reservation ID not found. Please try again.")
            speak_async(msg, lang)
            return False

        # Étape 3 : Suppression DB
        affected = execute_delete_id(idval)
        if affected and affected > 0:
            msg = (f"Réservation {idval} supprimée avec succès."
                   if lang == 'fr' else
                   f"Reservation {idval} deleted successfully.")
            speak_async(msg, lang)
            return True
        else:
            msg = (f"Aucune réservation trouvée avec l'identifiant {idval}."
                   if lang == 'fr' else
                   f"No reservation found with ID {idval}.")
            speak_async(msg, lang)
            return False

    except Exception as e:
        print(f'[Delete] Erreur: {e}')
        speak_async("Erreur lors du traitement audio.", 'fr')
        return False
    finally:
        try:
            os.remove(audio_path)
        except Exception:
            pass

# ─────────────────────────────────────────────
# Routes Flask
# ─────────────────────────────────────────────
@app.route('/start-add-recording', methods=['POST'])
def start_add():
    ok, msg = start_recording(mode='add')
    return jsonify({'status': 'success' if ok else 'error', 'message': msg})

@app.route('/stop-add-recording', methods=['POST'])
def stop_add():
    filename, mode = stop_recording_and_save()
    if not filename:
        return jsonify({'status': 'error', 'message': 'Aucun enregistrement trouvé'})
    threading.Thread(target=process_add_command, args=(filename,), daemon=True).start()
    return jsonify({'status': 'success', 'message': 'Enregistrement reçu, traitement en cours'})

@app.route('/start-delete-recording', methods=['POST'])
def start_delete():
    ok, msg = start_recording(mode='delete')
    return jsonify({'status': 'success' if ok else 'error', 'message': msg})

@app.route('/stop-delete-recording', methods=['POST'])
def stop_delete():
    filename, mode = stop_recording_and_save()
    if not filename:
        return jsonify({'status': 'error', 'message': 'Aucun enregistrement trouvé'})
    threading.Thread(target=process_delete_command, args=(filename,), daemon=True).start()
    return jsonify({'status': 'success', 'message': 'Enregistrement reçu, traitement en cours'})

@app.route('/health', methods=['GET'])
def health():
    """Endpoint de vérification — utile pour le debug."""
    return jsonify({
        'status': 'ok',
        'whisper_model': WHISPER_MODEL_SIZE,
        'recording': _rec['stream'] is not None,
    })

# ─────────────────────────────────────────────
# Interface Tkinter
# ─────────────────────────────────────────────
def run_flask():
    app.run(host='127.0.0.1', port=5000, use_reloader=False)

class VoiceAddApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title('Réservation par voix — Skillora')
        self.geometry('460x320')
        self.configure(bg='#f5f5f5')
        self._recording = False

        tk.Label(self, text='Gestion réservations vocale',
                 font=('Arial', 16, 'bold'), bg='#f5f5f5').pack(pady=14)

        # Bouton Ajout
        self.add_btn = tk.Button(
            self, text='🎙️  Ajouter par voix',
            font=('Arial', 13), width=22,
            command=self.toggle_add,
            bg='#1976D2', fg='white', activebackground='#0D47A1', relief='flat'
        )
        self.add_btn.pack(pady=6)

        # Bouton Suppression
        self.del_btn = tk.Button(
            self, text='🎙️  Supprimer par voix',
            font=('Arial', 13), width=22,
            command=self.toggle_delete,
            bg='#C62828', fg='white', activebackground='#7F0000', relief='flat'
        )
        self.del_btn.pack(pady=6)

        self.status_label = tk.Label(
            self, text='Prêt', font=('Arial', 11),
            bg='#f5f5f5', fg='#555'
        )
        self.status_label.pack(pady=10)

        self._mode = None  # 'add' ou 'delete'
        threading.Thread(target=run_flask, daemon=True).start()

    # ── Ajout ──
    def toggle_add(self):
        if not self._recording:
            self._start('add')
        else:
            self._stop()

    # ── Suppression ──
    def toggle_delete(self):
        if not self._recording:
            self._start('delete')
        else:
            self._stop()

    def _start(self, mode: str):
        url = f'http://127.0.0.1:5000/start-{mode}-recording'
        try:
            data = requests.post(url, timeout=3).json()
            if data['status'] == 'success':
                self._recording = True
                self._mode      = mode
                if mode == 'add':
                    self.add_btn.config(text='⏹️  Arrêter', bg='#E53935')
                    prompt = 'Dites prénom, nom et numéro de téléphone'
                else:
                    self.del_btn.config(text='⏹️  Arrêter', bg='#E53935')
                    prompt = 'Dites le numéro de réservation à supprimer'
                self.status_label.config(text=f'🔴 Enregistrement… {prompt}')
                speak_async(prompt, 'fr')
            else:
                messagebox.showerror('Erreur', data.get('message', 'Erreur'))
        except Exception as e:
            messagebox.showerror('Connexion', f'Impossible de démarrer : {e}')

    def _stop(self):
        url = f'http://127.0.0.1:5000/stop-{self._mode}-recording'
        try:
            data = requests.post(url, timeout=3).json()
            if data['status'] == 'success':
                self._recording = False
                self.add_btn.config(text='🎙️  Ajouter par voix',    bg='#1976D2')
                self.del_btn.config(text='🎙️  Supprimer par voix',  bg='#C62828')
                self.status_label.config(text='⏳ Traitement en cours…')
                # Remettre "Prêt" après 4 s (le traitement est asynchrone)
                self.after(4000, lambda: self.status_label.config(text='Prêt'))
            else:
                messagebox.showerror('Erreur', data.get('message', 'Erreur'))
        except Exception as e:
            messagebox.showerror('Connexion', f'Impossible d\'arrêter : {e}')


if __name__ == '__main__':
    gui = VoiceAddApp()
    gui.mainloop()