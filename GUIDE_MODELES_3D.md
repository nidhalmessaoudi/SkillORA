# 🎨 Guide d'utilisation des Modèles 3D - SkillHarbor

## 📋 Vue d'ensemble

Ce guide explique comment utiliser le système de gestion des modèles 3D pour les Salles et Events dans SkillHarbor (Symfony 6.4).

---

## ✅ Fonctionnalités implémentées

### 1. **Upload de fichiers 3D**
- Formats supportés : `.glb` et `.gltf`
- Taille maximale : Définie par la configuration PHP
- Stockage : `public/uploads/salles/`
- Nommage : Fichiers renommés avec un hash unique (sécurité)

### 2. **Stockage en base de données**
- Seul le **chemin relatif** est stocké (ex: `/uploads/salles/abc123.glb`)
- Champ : `image_3d` dans la table `salle`
- Type : `VARCHAR(255)`, nullable

### 3. **Affichage dynamique**
- Visualisation 3D avec Three.js
- Contrôles souris (OrbitControls)
- Contrôles clavier ZQSD pour navigation
- Responsive et adaptatif

---

## 🚀 Comment utiliser

### **A. Créer une Salle avec modèle 3D**

#### 1. Via l'interface Admin
```
http://127.0.0.1:8000/admin/salles/new
```

**Étapes :**
1. Remplir les informations de la salle (nom, location, participants, etc.)
2. Dans le champ **"3D Model (.glb/.gltf)"**, cliquer sur "Parcourir"
3. Sélectionner votre fichier `.glb` ou `.gltf`
4. Cliquer sur **"Create Salle"**

#### 2. Le système va automatiquement :
- ✅ Valider le format du fichier
- ✅ Générer un nom unique (ex: `a1b2c3d4e5f6.glb`)
- ✅ Déplacer le fichier vers `public/uploads/salles/`
- ✅ Enregistrer le chemin `/uploads/salles/a1b2c3d4e5f6.glb` en base de données

---

### **B. Afficher une Salle avec son modèle 3D**

#### URL publique :
```
http://127.0.0.1:8000/salles/{id}
```

**Exemple :**
```
http://127.0.0.1:8000/salles/1
```

#### Contrôles 3D :
- **Souris** : Clic gauche + glisser pour pivoter
- **Z** : Avancer
- **S** : Reculer
- **Q** : Déplacer à gauche
- **D** : Déplacer à droite
- **Molette** : Zoom in/out

---

### **C. Modifier le modèle 3D d'une Salle**

```
http://127.0.0.1:8000/admin/salles/{id}/edit
```

**Étapes :**
1. Aller sur la page d'édition
2. Uploader un nouveau fichier 3D (optionnel)
3. Si un nouveau fichier est uploadé, il remplace l'ancien chemin
4. Cliquer sur **"Update Salle"**

---

## 🔧 Architecture technique

### **1. Entity Salle**
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

### **2. Controller - Upload**
```php
private function handleModelUpload(Request $request): ?string
{
    $file = $request->files->get('image_3d');
    if (!$file instanceof UploadedFile || !$file->isValid()) {
        return null;
    }

    $extension = strtolower((string) $file->getClientOriginalExtension());
    if (!in_array($extension, ['glb', 'gltf'], true)) {
        $this->addFlash('error', 'Only .glb or .gltf files are allowed.');
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/salles';

    $file->move($targetDir, $filename);
    return '/uploads/salles/' . $filename;
}
```

### **3. Template - Affichage dynamique**
```twig
{% if salle.image3d %}
<script type="module">
    const modelUrl = "{{ salle.image3d|e('js') }}";
    
    loader.load(
        modelUrl,  // ✅ Variable dynamique depuis la BDD
        (gltf) => {
            scene.add(gltf.scene);
        }
    );
</script>
{% endif %}
```

---

## 📁 Structure des fichiers

```
skillharbor/
├── public/
│   └── uploads/
│       └── salles/              # 📂 Modèles 3D uploadés
│           ├── a1b2c3d4.glb
│           └── e5f6g7h8.gltf
├── src/
│   ├── Entity/
│   │   └── Salle.php            # 🗃️ Entity avec champ image_3d
│   └── Controller/
│       ├── SalleController.php  # 🎛️ Gestion admin
│       └── PublicSalleController.php  # 🌐 Affichage public
└── templates/
    └── pages/
        └── salles/
            └── show.html.twig   # 🎨 Visualisation 3D
```

---

## ⚠️ Bonnes pratiques

### ✅ À FAIRE
- Utiliser des fichiers `.glb` (plus optimisés que `.gltf`)
- Compresser les modèles 3D avant upload (Blender, etc.)
- Tester le modèle localement avant upload
- Vérifier que le fichier fait moins de 10 MB

### ❌ À ÉVITER
- Ne pas uploader de fichiers trop lourds (> 50 MB)
- Ne pas modifier manuellement les noms de fichiers dans `public/uploads/salles/`
- Ne pas éditer directement le champ `image_3d` en base de données

---

## 🐛 Dépannage

### Problème : Le modèle 3D ne s'affiche pas

**Solutions :**
1. Vérifier que le fichier existe dans `public/uploads/salles/`
2. Vérifier le chemin en base de données (doit commencer par `/uploads/salles/`)
3. Ouvrir la console du navigateur (F12) pour voir les erreurs
4. Vérifier que le fichier est bien au format `.glb` ou `.gltf`

### Problème : Erreur lors de l'upload

**Solutions :**
1. Vérifier que le dossier `public/uploads/salles/` existe
2. Vérifier les permissions du dossier (doit être accessible en écriture)
3. Vérifier la taille du fichier (limite PHP `upload_max_filesize`)

---

## 📚 Ressources

- **Three.js Documentation** : https://threejs.org/docs/
- **GLTF Format** : https://www.khronos.org/gltf/
- **Blender (pour créer des modèles)** : https://www.blender.org/

---

**Auteur** : SkillHarbor Team  
**Version** : 1.0  
**Date** : 2026-02-07

