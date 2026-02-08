# 🌐 URLs et Routes - Système 3D

## 📋 Liste complète des URLs

### **🔐 Administration (ROLE_ADMIN requis)**

#### **Liste des salles**
```
URL: http://127.0.0.1:8000/admin/salles
Route: admin_salles_index
Méthode: GET
Description: Affiche la liste de toutes les salles
```

#### **Créer une salle**
```
URL: http://127.0.0.1:8000/admin/salles/new
Route: admin_salles_new
Méthode: GET, POST
Description: Formulaire de création d'une salle avec upload de modèle 3D
```

**Exemple de formulaire :**
```html
<form method="post" enctype="multipart/form-data">
    <input type="text" name="name" placeholder="Nom de la salle" required>
    <input type="text" name="location" placeholder="Localisation" required>
    <input type="number" name="max_participants" placeholder="Max participants" required>
    <input type="number" name="duration" placeholder="Durée (minutes)" required>
    <input type="file" name="image_3d" accept=".glb,.gltf">
    <button type="submit">Créer</button>
</form>
```

---

#### **Afficher une salle (Admin)**
```
URL: http://127.0.0.1:8000/admin/salles/{id}
Route: admin_salles_show
Méthode: GET
Description: Affiche les détails d'une salle avec aperçu 3D
Exemple: http://127.0.0.1:8000/admin/salles/1
```

**Fonctionnalités :**
- ✅ Informations de la salle
- ✅ Aperçu du modèle 3D
- ✅ Boutons "Edit" et "Delete"

---

#### **Modifier une salle**
```
URL: http://127.0.0.1:8000/admin/salles/{id}/edit
Route: admin_salles_edit
Méthode: GET, POST
Description: Formulaire d'édition avec possibilité de changer le modèle 3D
Exemple: http://127.0.0.1:8000/admin/salles/1/edit
```

**Fonctionnalités :**
- ✅ Modification des informations
- ✅ Upload d'un nouveau modèle 3D (optionnel)
- ✅ Conservation de l'ancien modèle si pas de nouveau fichier

---

#### **Supprimer une salle**
```
URL: http://127.0.0.1:8000/admin/salles/{id}/delete
Route: admin_salles_delete
Méthode: POST
Description: Supprime une salle
Exemple: http://127.0.0.1:8000/admin/salles/1/delete
```

---

### **🌍 Public (Accessible à tous)**

#### **Afficher une salle (Public)**
```
URL: http://127.0.0.1:8000/salles/{id}
Route: salles_show
Méthode: GET
Description: Affiche la salle avec modèle 3D interactif
Exemple: http://127.0.0.1:8000/salles/1
```

**Fonctionnalités :**
- ✅ Informations de la salle
- ✅ Modèle 3D interactif avec contrôles ZQSD
- ✅ Bouton "Reservation"

---

## 🎯 Exemples d'utilisation

### **Scénario 1 : Créer une salle avec modèle 3D**

1. **Accéder au formulaire**
   ```
   http://127.0.0.1:8000/admin/salles/new
   ```

2. **Remplir le formulaire**
   - Nom : "Grande Salle de Conférence"
   - Location : "Bâtiment A, Étage 2"
   - Max Participants : 100
   - Duration : 120 minutes
   - 3D Model : Uploader `conference_room.glb`

3. **Soumettre le formulaire**
   - Le fichier est déplacé vers `public/uploads/salles/abc123.glb`
   - Le chemin `/uploads/salles/abc123.glb` est enregistré en BDD

4. **Redirection automatique**
   ```
   http://127.0.0.1:8000/admin/salles/1
   ```

---

### **Scénario 2 : Afficher une salle**

1. **Accéder à la page publique**
   ```
   http://127.0.0.1:8000/salles/1
   ```

2. **Résultat**
   - Informations de la salle affichées
   - Modèle 3D chargé depuis `/uploads/salles/abc123.glb`
   - Contrôles interactifs disponibles

---

### **Scénario 3 : Modifier le modèle 3D**

1. **Accéder au formulaire d'édition**
   ```
   http://127.0.0.1:8000/admin/salles/1/edit
   ```

2. **Uploader un nouveau fichier**
   - Sélectionner `new_conference_room.glb`
   - Soumettre le formulaire

3. **Résultat**
   - Nouveau fichier déplacé vers `public/uploads/salles/def456.glb`
   - Chemin mis à jour en BDD : `/uploads/salles/def456.glb`
   - Ancien fichier conservé (ou supprimé selon la logique)

---

## 📁 Structure des fichiers

### **Fichiers uploadés**
```
public/uploads/salles/
├── a1b2c3d4e5f6g7h8.glb  ← Salle 1
├── i9j0k1l2m3n4o5p6.glb  ← Salle 2
└── q7r8s9t0u1v2w3x4.glb  ← Salle 3
```

### **Base de données**
```sql
SELECT id, name, image_3d FROM salle;

+----+---------------------------+----------------------------------+
| id | name                      | image_3d                         |
+----+---------------------------+----------------------------------+
|  1 | Grande Salle              | /uploads/salles/a1b2c3d4.glb     |
|  2 | Petite Salle              | /uploads/salles/i9j0k1l2.glb     |
|  3 | Salle de Réunion          | /uploads/salles/q7r8s9t0.glb     |
+----+---------------------------+----------------------------------+
```

---

## 🔧 Paramètres de requête

### **Aucun paramètre GET requis**

Toutes les routes utilisent des paramètres d'URL (path parameters) :

```
/salles/{id}           ← ID de la salle
/admin/salles/{id}     ← ID de la salle
/admin/salles/{id}/edit ← ID de la salle
```

---

## 📊 Codes de réponse HTTP

### **Succès**
- `200 OK` : Page affichée avec succès
- `302 Found` : Redirection après création/modification

### **Erreurs**
- `404 Not Found` : Salle non trouvée
- `403 Forbidden` : Accès refusé (pas de ROLE_ADMIN)
- `500 Internal Server Error` : Erreur serveur

---

## 🎨 Exemples de réponses

### **Page publique d'une salle**

```html
<!-- http://127.0.0.1:8000/salles/1 -->

<div class="container">
    <h1>Grande Salle de Conférence</h1>
    
    <div>
        <p><strong>Location:</strong> Bâtiment A, Étage 2</p>
        <p><strong>Max Participants:</strong> 100</p>
        <p><strong>Duration:</strong> 120 minutes</p>
    </div>

    <div id="scene-container"></div>
    <p>🎮 Contrôles: Z Q S D pour se déplacer | Souris pour pivoter</p>
    <p>📁 Fichier: /uploads/salles/abc123.glb</p>

    <script type="module">
        const modelUrl = "/uploads/salles/abc123.glb";
        loader.load(modelUrl, (gltf) => {
            scene.add(gltf.scene);
        });
    </script>
</div>
```

---

### **Page admin d'une salle**

```html
<!-- http://127.0.0.1:8000/admin/salles/1 -->

<div class="admin-container">
    <h1>Grande Salle de Conférence</h1>
    
    <div class="grid">
        <div class="info">
            <p><strong>Location:</strong> Bâtiment A, Étage 2</p>
            <p><strong>Max Participants:</strong> 100</p>
            <p><strong>3D Model:</strong> /uploads/salles/abc123.glb</p>
        </div>

        <div class="preview">
            <h2>Aperçu 3D</h2>
            <div id="scene-container"></div>
        </div>
    </div>

    <div class="actions">
        <a href="/admin/salles/1/edit">Edit</a>
        <form method="post" action="/admin/salles/1/delete">
            <button type="submit">Delete</button>
        </form>
    </div>
</div>
```

---

## 🚀 Commandes utiles

### **Voir toutes les routes**
```bash
php bin/console debug:router
```

### **Voir les routes des salles**
```bash
php bin/console debug:router | grep salle
```

**Résultat attendu :**
```
admin_salles_index    GET      /admin/salles
admin_salles_new      GET|POST /admin/salles/new
admin_salles_show     GET      /admin/salles/{id}
admin_salles_edit     GET|POST /admin/salles/{id}/edit
admin_salles_delete   POST     /admin/salles/{id}/delete
salles_show           GET      /salles/{id}
```

---

## ✅ Checklist de vérification

- [ ] Toutes les routes sont accessibles
- [ ] Le formulaire de création fonctionne
- [ ] L'upload de fichiers 3D fonctionne
- [ ] Le modèle 3D s'affiche sur la page publique
- [ ] Le modèle 3D s'affiche sur la page admin
- [ ] Les contrôles ZQSD fonctionnent
- [ ] Les contrôles souris fonctionnent
- [ ] La modification du modèle 3D fonctionne
- [ ] La suppression d'une salle fonctionne

---

**Auteur** : SkillHarbor Team  
**Date** : 2026-02-07

