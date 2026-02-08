# 📦 Exemples d'intégration des modèles 3D

## 🎯 Exemple 1 : Affichage simple dans une page Twig

```twig
{# templates/pages/salles/show.html.twig #}

{% extends 'layouts/base.html.twig' %}

{% block body %}
<div class="container">
    <h1>{{ salle.name }}</h1>
    
    {% if salle.image3d %}
        <div id="scene-container" style="width: 100%; height: 500px;"></div>
    {% else %}
        <p>Aucun modèle 3D disponible</p>
    {% endif %}
</div>
{% endblock %}

{% block javascripts %}
{% if salle.image3d %}
<script type="module">
    import * as THREE from "three";
    import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
    import { OrbitControls } from "three/addons/controls/OrbitControls.js";

    // ✅ VARIABLE DYNAMIQUE depuis la base de données
    const modelUrl = "{{ salle.image3d|e('js') }}";
    
    const container = document.getElementById("scene-container");
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x333333);

    const camera = new THREE.PerspectiveCamera(
        75, 
        container.clientWidth / container.clientHeight, 
        0.1, 
        1000
    );
    camera.position.set(0, 5, 10);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    container.appendChild(renderer.domElement);

    // Lumières
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
    directionalLight.position.set(5, 5, 5);
    scene.add(directionalLight);

    // Chargement du modèle 3D
    const loader = new GLTFLoader();
    let controls;

    loader.load(
        modelUrl,  // ✅ Utilisation de la variable dynamique
        (gltf) => {
            scene.add(gltf.scene);
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
        },
        undefined,
        (error) => console.error("Erreur de chargement:", error)
    );

    function animate() {
        if (controls) controls.update();
        renderer.render(scene, camera);
        requestAnimationFrame(animate);
    }
    animate();
</script>
{% endif %}
{% endblock %}
```

---

## 🎯 Exemple 2 : Formulaire d'upload (Admin)

```twig
{# templates/pages/admin/salles/new.html.twig #}

<form method="post" enctype="multipart/form-data">
    <div>
        <label for="name">Nom de la salle</label>
        <input type="text" id="name" name="name" required>
    </div>

    <div>
        <label for="location">Localisation</label>
        <input type="text" id="location" name="location" required>
    </div>

    <div>
        <label for="max_participants">Nombre max de participants</label>
        <input type="number" id="max_participants" name="max_participants" required>
    </div>

    <div>
        <label for="duration">Durée (minutes)</label>
        <input type="number" id="duration" name="duration" required>
    </div>

    {# ✅ CHAMP UPLOAD 3D #}
    <div>
        <label for="image_3d">Modèle 3D (.glb ou .gltf)</label>
        <input type="file" id="image_3d" name="image_3d" accept=".glb,.gltf">
        <p class="hint">Formats acceptés : .glb, .gltf (max 50 MB)</p>
    </div>

    <button type="submit">Créer la salle</button>
</form>
```

---

## 🎯 Exemple 3 : Controller - Gestion de l'upload

```php
// src/Controller/SalleController.php

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/admin/salles/new', name: 'admin_salles_new')]
public function new(Request $request): Response
{
    if ($request->isMethod('POST')) {
        // ✅ Récupération du fichier uploadé
        $modelPath = $this->handleModelUpload($request);

        $salle = new Salle();
        $salle->setName($request->request->get('name'));
        $salle->setLocation($request->request->get('location'));
        $salle->setMaxParticipants((int) $request->request->get('max_participants'));
        $salle->setDuration((int) $request->request->get('duration'));
        
        // ✅ Enregistrement du CHEMIN du fichier 3D
        $salle->setImage3d($modelPath);

        $this->entityManager->persist($salle);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin_salles_show', ['id' => $salle->getId()]);
    }

    return $this->render('pages/admin/salles/new.html.twig');
}

// ✅ Méthode de gestion de l'upload
private function handleModelUpload(Request $request): ?string
{
    $file = $request->files->get('image_3d');
    
    // Vérification du fichier
    if (!$file instanceof UploadedFile || !$file->isValid()) {
        return null;
    }

    // Validation de l'extension
    $extension = strtolower((string) $file->getClientOriginalExtension());
    if (!in_array($extension, ['glb', 'gltf'], true)) {
        $this->addFlash('error', 'Seuls les fichiers .glb et .gltf sont acceptés.');
        return null;
    }

    // Génération d'un nom unique
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    // Dossier de destination
    $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/salles';

    try {
        // Déplacement du fichier
        $file->move($targetDir, $filename);
    } catch (FileException $e) {
        $this->addFlash('error', 'Erreur lors de l\'upload du fichier 3D.');
        return null;
    }

    // ✅ Retour du CHEMIN RELATIF (stocké en BDD)
    return '/uploads/salles/' . $filename;
}
```

---

## 🎯 Exemple 4 : Affichage avec contrôles ZQSD

```javascript
// Ajout des contrôles clavier ZQSD
const moveSpeed = 0.08;
const keysPressed = {};

window.addEventListener('keydown', (e) => {
    const key = e.key.toLowerCase();
    if (['z', 'q', 's', 'd'].includes(key)) {
        keysPressed[key] = true;
    }
});

window.addEventListener('keyup', (e) => {
    const key = e.key.toLowerCase();
    if (['z', 'q', 's', 'd'].includes(key)) {
        keysPressed[key] = false;
    }
});

function animate() {
    // Calcul des vecteurs de direction
    const direction = new THREE.Vector3();
    camera.getWorldDirection(direction);
    direction.y = 0;
    direction.normalize();
    
    const right = new THREE.Vector3();
    right.crossVectors(camera.up, direction).normalize();

    // Mouvements
    if (keysPressed['z']) camera.position.addScaledVector(direction, moveSpeed);
    if (keysPressed['s']) camera.position.addScaledVector(direction, -moveSpeed);
    if (keysPressed['q']) camera.position.addScaledVector(right, moveSpeed);
    if (keysPressed['d']) camera.position.addScaledVector(right, -moveSpeed);

    if (controls) controls.update();
    renderer.render(scene, camera);
    requestAnimationFrame(animate);
}
```

---

## 📊 Flux de données

```
┌─────────────────────────────────────────────────────────────┐
│                    UPLOAD DE FICHIER 3D                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  1. Utilisateur sélectionne un fichier .glb ou .gltf        │
│     via le formulaire HTML                                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Controller reçoit le fichier via Request                │
│     $file = $request->files->get('image_3d');               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Validation du format (.glb ou .gltf)                    │
│     if (!in_array($extension, ['glb', 'gltf']))             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  4. Génération d'un nom unique                              │
│     $filename = bin2hex(random_bytes(16)) . '.' . $ext;     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  5. Déplacement du fichier vers public/uploads/salles/      │
│     $file->move($targetDir, $filename);                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  6. Enregistrement du CHEMIN en base de données             │
│     $salle->setImage3d('/uploads/salles/abc123.glb');       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  7. Affichage dans le template Twig                         │
│     const modelUrl = "{{ salle.image3d|e('js') }}";         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  8. Three.js charge le modèle depuis l'URL                  │
│     loader.load(modelUrl, (gltf) => { ... });               │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ Points clés à retenir

1. **Nom fixe → Variable dynamique** : Remplacer `"venue_stage.glb"` par `{{ salle.image3d }}`
2. **Upload via formulaire** : Utiliser `<input type="file" name="image_3d" accept=".glb,.gltf">`
3. **Stockage du chemin** : Enregistrer `/uploads/salles/abc123.glb` en BDD (pas le fichier complet)
4. **Affichage dynamique** : Three.js charge le modèle depuis l'URL stockée
5. **Sécurité** : Valider le format et générer un nom unique

---

**Auteur** : SkillHarbor Team  
**Date** : 2026-02-07

