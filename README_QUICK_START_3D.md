# 🚀 Quick Start - Système 3D

## ⚡ Démarrage rapide (5 minutes)

### **Étape 1 : Créer une salle avec modèle 3D**

1. Démarrer le serveur Symfony :
   ```bash
   symfony server:start
   ```

2. Accéder au formulaire de création :
   ```
   http://127.0.0.1:8000/admin/salles/new
   ```

3. Remplir le formulaire :
   - **Nom** : "Grande Salle"
   - **Location** : "Bâtiment A"
   - **Max Participants** : 100
   - **Duration** : 120
   - **3D Model** : Uploader un fichier `.glb` ou `.gltf`

4. Cliquer sur **"Create Salle"**

---

### **Étape 2 : Afficher la salle**

1. Accéder à la page publique :
   ```
   http://127.0.0.1:8000/salles/1
   ```

2. **Résultat attendu :**
   - ✅ Informations de la salle affichées
   - ✅ Modèle 3D visible et interactif
   - ✅ Contrôles fonctionnels :
     - **Souris** : Rotation du modèle
     - **Z** : Avancer
     - **S** : Reculer
     - **Q** : Gauche
     - **D** : Droite

---

## 📚 Documentation complète

### **8 fichiers de documentation disponibles :**

1. **`INDEX_DOCUMENTATION_3D.md`** - Point d'entrée (commencer ici)
2. **`README_SYSTEME_3D.md`** - Vue d'ensemble du système
3. **`GUIDE_MODELES_3D.md`** - Guide complet d'utilisation
4. **`EXEMPLE_INTEGRATION_3D.md`** - Exemples de code
5. **`RESUME_IMPLEMENTATION.md`** - Ce qui a été fait
6. **`AVANT_APRES_COMPARAISON.md`** - Comparaison avant/après
7. **`CHECKLIST_VERIFICATION_3D.md`** - Tests et vérifications
8. **`URLS_ET_ROUTES.md`** - Liste des URLs

---

## ✅ Ce qui a été fait

### **1. Modification des templates**

- ✅ `templates/pages/salles/show.html.twig` - Ajout contrôles ZQSD
- ✅ `templates/pages/admin/salles/show.html.twig` - Ajout aperçu 3D

### **2. Système déjà en place (pas de modification)**

- ✅ Entity `Salle` avec champ `image_3d`
- ✅ Controller avec méthode `handleModelUpload()`
- ✅ Formulaires d'upload (new.html.twig, edit.html.twig)
- ✅ Routes admin et publiques

---

## 🎯 Fonctionnalités

### **Upload de fichiers 3D**
- ✅ Formats supportés : `.glb`, `.gltf`
- ✅ Validation automatique
- ✅ Génération de noms uniques
- ✅ Stockage dans `public/uploads/salles/`

### **Affichage dynamique**
- ✅ Chargement depuis la BDD
- ✅ Visualisation 3D avec Three.js
- ✅ Contrôles souris (OrbitControls)
- ✅ Contrôles clavier (ZQSD)

### **Interface admin**
- ✅ Création de salles
- ✅ Modification de salles
- ✅ Aperçu 3D dans l'admin
- ✅ Suppression de salles

---

## 🔧 Architecture

### **Flux de données**

```
Formulaire → Upload → Validation → Stockage fichier → BDD (chemin) → Affichage 3D
```

### **Exemple de code**

```javascript
// Variable dynamique depuis la BDD
const modelUrl = "{{ salle.image3d|e('js') }}";

// Chargement du modèle
loader.load(modelUrl, (gltf) => {
    scene.add(gltf.scene);
});
```

---

## 🐛 Dépannage rapide

### **Le modèle ne s'affiche pas**

1. Vérifier que le fichier existe :
   ```bash
   ls -la public/uploads/salles/
   ```

2. Vérifier la console du navigateur (F12)

3. Vérifier le chemin en BDD :
   ```sql
   SELECT id, name, image_3d FROM salle WHERE id = 1;
   ```

---

### **Erreur lors de l'upload**

1. Vérifier les permissions :
   ```bash
   chmod 755 public/uploads/salles/
   ```

2. Vérifier les logs :
   ```bash
   tail -f var/log/dev.log
   ```

---

## 📞 Support

### **Documentation complète**
Consulter `INDEX_DOCUMENTATION_3D.md` pour accéder à tous les documents.

### **Commandes utiles**

```bash
# Vérifier la BDD
php bin/console doctrine:schema:validate

# Voir les routes
php bin/console debug:router | grep salle

# Vider le cache
php bin/console cache:clear
```

---

## ✅ Validation

Le système fonctionne si :

- ✅ Vous pouvez créer une salle avec un modèle 3D
- ✅ Le modèle s'affiche sur `/salles/{id}`
- ✅ Les contrôles ZQSD fonctionnent
- ✅ Les contrôles souris fonctionnent

---

## 🎉 Résultat final

Vous avez maintenant un **système complet de gestion des modèles 3D** :

- ✅ Upload de fichiers 3D
- ✅ Stockage du chemin en BDD
- ✅ Affichage dynamique avec Three.js
- ✅ Contrôles interactifs
- ✅ Interface admin complète
- ✅ Documentation exhaustive (8 fichiers)

**Tout fonctionne ! 🚀**

---

**Auteur** : SkillHarbor Team  
**Version** : 1.0  
**Date** : 2026-02-07

