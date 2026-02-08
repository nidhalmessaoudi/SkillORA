# 🏗️ Structure du Projet - Système 3D

## 📁 Arborescence complète

```
skillharbor/
│
├── 📚 DOCUMENTATION (9 fichiers créés)
│   ├── INDEX_DOCUMENTATION_3D.md          ← Point d'entrée
│   ├── README_QUICK_START_3D.md           ← Démarrage rapide
│   ├── README_SYSTEME_3D.md               ← Vue d'ensemble
│   ├── GUIDE_MODELES_3D.md                ← Guide complet
│   ├── EXEMPLE_INTEGRATION_3D.md          ← Exemples de code
│   ├── RESUME_IMPLEMENTATION.md           ← Ce qui a été fait
│   ├── AVANT_APRES_COMPARAISON.md         ← Comparaison
│   ├── CHECKLIST_VERIFICATION_3D.md       ← Tests
│   ├── URLS_ET_ROUTES.md                  ← Routes
│   └── STRUCTURE_PROJET_3D.md             ← Ce fichier
│
├── 📂 src/
│   ├── Controller/
│   │   ├── SalleController.php            ✅ Gestion admin
│   │   │   ├── index()                    → Liste des salles
│   │   │   ├── new()                      → Créer une salle
│   │   │   ├── show()                     → Afficher une salle (admin)
│   │   │   ├── edit()                     → Modifier une salle
│   │   │   ├── delete()                   → Supprimer une salle
│   │   │   └── handleModelUpload()        → Upload de fichiers 3D
│   │   │
│   │   ├── PublicSalleController.php      ✅ Affichage public
│   │   │   └── show()                     → Afficher une salle (public)
│   │   │
│   │   └── EventController.php            ✅ Gestion des events
│   │       └── handleSalleModelUpload()   → Upload 3D pour events
│   │
│   └── Entity/
│       └── Salle.php                      ✅ Entity avec champ image_3d
│           ├── $id
│           ├── $name
│           ├── $image3d                   ← Chemin du modèle 3D
│           ├── $maxParticipants
│           ├── $duration
│           ├── $equipment
│           └── $location
│
├── 📂 templates/
│   └── pages/
│       ├── salles/
│       │   └── show.html.twig             ✅ MODIFIÉ - Affichage public
│       │       ├── Informations de la salle
│       │       ├── Modèle 3D avec Three.js
│       │       ├── Contrôles ZQSD
│       │       └── Contrôles souris
│       │
│       └── admin/
│           └── salles/
│               ├── index.html.twig        ✅ Liste des salles
│               ├── new.html.twig          ✅ Formulaire de création
│               ├── show.html.twig         ✅ MODIFIÉ - Aperçu 3D
│               └── edit.html.twig         ✅ Formulaire d'édition
│
├── 📂 public/
│   └── uploads/
│       └── salles/                        ✅ Fichiers 3D uploadés
│           ├── a1b2c3d4e5f6g7h8.glb
│           ├── i9j0k1l2m3n4o5p6.glb
│           └── q7r8s9t0u1v2w3x4.glb
│
└── 📂 migrations/
    └── Version20260207140239.php          ✅ Migration BDD
```

---

## 🎯 Fichiers clés

### **1. Entity Salle** (`src/Entity/Salle.php`)

```php
#[ORM\Column(name: 'image_3d', type: 'string', length: 255, nullable: true)]
private ?string $image3d = null;

public function getImage3d(): ?string
{
    return $this->image3d;
}

public function setImage3d(?string $image3d): static
{
    $this->image3d = $image3d;
    return $this;
}
```

**Rôle :** Stocke le chemin du modèle 3D en base de données.

---

### **2. Controller Upload** (`src/Controller/SalleController.php`)

```php
private function handleModelUpload(Request $request): ?string
{
    $file = $request->files->get('image_3d');
    
    // Validation
    if (!$file instanceof UploadedFile || !$file->isValid()) {
        return null;
    }

    $extension = strtolower((string) $file->getClientOriginalExtension());
    if (!in_array($extension, ['glb', 'gltf'], true)) {
        return null;
    }

    // Génération nom unique
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/salles';

    // Déplacement
    $file->move($targetDir, $filename);

    // Retour du chemin
    return '/uploads/salles/' . $filename;
}
```

**Rôle :** Gère l'upload des fichiers 3D.

---

### **3. Template Public** (`templates/pages/salles/show.html.twig`)

```twig
{% if salle.image3d %}
<div id="scene-container" class="w-full h-[420px]"></div>

<script type="module">
    import * as THREE from "three";
    import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
    import { OrbitControls } from "three/addons/controls/OrbitControls.js";

    // ✅ Variable dynamique
    const modelUrl = "{{ salle.image3d|e('js') }}";

    // Chargement du modèle
    loader.load(modelUrl, (gltf) => {
        scene.add(gltf.scene);
    });

    // Contrôles ZQSD
    if (keysPressed['z']) camera.position.addScaledVector(direction, moveSpeed);
    if (keysPressed['s']) camera.position.addScaledVector(direction, -moveSpeed);
    if (keysPressed['q']) camera.position.addScaledVector(right, moveSpeed);
    if (keysPressed['d']) camera.position.addScaledVector(right, -moveSpeed);
</script>
{% endif %}
```

**Rôle :** Affiche le modèle 3D avec contrôles interactifs.

---

### **4. Formulaire Upload** (`templates/pages/admin/salles/new.html.twig`)

```html
<form method="post" enctype="multipart/form-data">
    <input type="text" name="name" required>
    <input type="text" name="location" required>
    <input type="number" name="max_participants" required>
    <input type="number" name="duration" required>
    
    <!-- ✅ Champ upload 3D -->
    <input type="file" name="image_3d" accept=".glb,.gltf">
    
    <button type="submit">Create Salle</button>
</form>
```

**Rôle :** Permet l'upload de fichiers 3D.

---

## 🔄 Flux de données

```
┌─────────────────────────────────────────────────────────────┐
│  1. Utilisateur accède au formulaire                        │
│     /admin/salles/new                                       │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  2. Upload du fichier .glb                                  │
│     <input type="file" name="image_3d">                     │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  3. Controller reçoit le fichier                            │
│     SalleController::handleModelUpload()                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  4. Validation du format                                    │
│     if (!in_array($extension, ['glb', 'gltf']))             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  5. Génération nom unique                                   │
│     bin2hex(random_bytes(16)) . '.glb'                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  6. Déplacement du fichier                                  │
│     public/uploads/salles/abc123.glb                        │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  7. Enregistrement du chemin en BDD                         │
│     $salle->setImage3d('/uploads/salles/abc123.glb')        │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  8. Affichage sur la page publique                          │
│     /salles/1                                               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  9. Template récupère le chemin                             │
│     const modelUrl = "{{ salle.image3d }}";                 │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  10. Three.js charge le modèle                              │
│      loader.load(modelUrl, (gltf) => { ... })               │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 Base de données

### **Table `salle`**

```sql
CREATE TABLE salle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    image_3d VARCHAR(255) DEFAULT NULL,  ← Chemin du modèle 3D
    max_participants INT NOT NULL,
    duration INT NOT NULL,
    equipment TEXT DEFAULT NULL,
    location VARCHAR(255) NOT NULL,
    event_id INT DEFAULT NULL
);
```

### **Exemple de données**

```sql
SELECT id, name, image_3d FROM salle;

+----+---------------------------+----------------------------------+
| id | name                      | image_3d                         |
+----+---------------------------+----------------------------------+
|  1 | Grande Salle              | /uploads/salles/a1b2c3d4.glb     |
|  2 | Petite Salle              | /uploads/salles/e5f6g7h8.glb     |
|  3 | Salle de Réunion          | NULL                             |
+----+---------------------------+----------------------------------+
```

---

## 🌐 Routes

```
GET    /admin/salles              → Liste des salles
GET    /admin/salles/new          → Formulaire de création
POST   /admin/salles/new          → Créer une salle
GET    /admin/salles/{id}         → Afficher une salle (admin)
GET    /admin/salles/{id}/edit    → Formulaire d'édition
POST   /admin/salles/{id}/edit    → Modifier une salle
POST   /admin/salles/{id}/delete  → Supprimer une salle
GET    /salles/{id}               → Afficher une salle (public)
```

---

## ✅ Résumé

### **Fichiers modifiés**
- ✅ `templates/pages/salles/show.html.twig` (contrôles ZQSD)
- ✅ `templates/pages/admin/salles/show.html.twig` (aperçu 3D)

### **Fichiers déjà en place**
- ✅ `src/Entity/Salle.php` (champ image_3d)
- ✅ `src/Controller/SalleController.php` (upload)
- ✅ `templates/pages/admin/salles/new.html.twig` (formulaire)
- ✅ `templates/pages/admin/salles/edit.html.twig` (formulaire)

### **Documentation créée**
- ✅ 9 fichiers de documentation complets

---

**Auteur** : SkillHarbor Team  
**Date** : 2026-02-07

