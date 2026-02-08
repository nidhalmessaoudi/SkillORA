# ✅ Checklist de vérification du système 3D

## 📋 Vérifications à effectuer

### 1. **Structure des fichiers**

- [ ] Le dossier `public/uploads/salles/` existe
- [ ] Le dossier a les bonnes permissions (lecture/écriture)
- [ ] Des fichiers `.glb` sont présents dans le dossier

**Commande de vérification :**
```bash
ls -la public/uploads/salles/
```

---

### 2. **Base de données**

- [ ] La table `salle` existe
- [ ] Le champ `image_3d` existe (VARCHAR 255, nullable)
- [ ] Au moins une salle a un chemin 3D enregistré

**Commande de vérification :**
```bash
php bin/console doctrine:schema:validate
```

**Requête SQL de test :**
```sql
SELECT id, name, image_3d FROM salle WHERE image_3d IS NOT NULL;
```

---

### 3. **Templates**

- [ ] `templates/pages/salles/show.html.twig` contient le code Three.js
- [ ] La variable `{{ salle.image3d }}` est utilisée (pas de nom fixe)
- [ ] Le bloc `{% block javascripts %}` est présent
- [ ] Les contrôles ZQSD sont implémentés

**Vérification :**
```bash
grep -n "salle.image3d" templates/pages/salles/show.html.twig
```

---

### 4. **Controllers**

- [ ] `SalleController.php` a la méthode `handleModelUpload()`
- [ ] La méthode valide les formats `.glb` et `.gltf`
- [ ] Le chemin est enregistré avec `setImage3d()`

**Vérification :**
```bash
grep -n "handleModelUpload" src/Controller/SalleController.php
```

---

### 5. **Formulaires**

- [ ] Le formulaire de création a un champ `<input type="file" name="image_3d">`
- [ ] L'attribut `accept=".glb,.gltf"` est présent
- [ ] Le formulaire a `enctype="multipart/form-data"`

**Vérification :**
```bash
grep -n "image_3d" templates/pages/admin/salles/new.html.twig
```

---

### 6. **Tests fonctionnels**

#### Test 1 : Créer une salle avec modèle 3D

1. [ ] Aller sur `http://127.0.0.1:8000/admin/salles/new`
2. [ ] Remplir le formulaire
3. [ ] Uploader un fichier `.glb`
4. [ ] Cliquer sur "Create Salle"
5. [ ] Vérifier que la salle est créée
6. [ ] Vérifier que le fichier est dans `public/uploads/salles/`
7. [ ] Vérifier que le chemin est en BDD

#### Test 2 : Afficher une salle avec modèle 3D

1. [ ] Aller sur `http://127.0.0.1:8000/salles/1` (remplacer 1 par un ID valide)
2. [ ] Vérifier que le modèle 3D s'affiche
3. [ ] Tester les contrôles souris (rotation)
4. [ ] Tester les contrôles clavier (ZQSD)
5. [ ] Vérifier que le modèle se charge sans erreur (F12 → Console)

#### Test 3 : Modifier le modèle 3D d'une salle

1. [ ] Aller sur `http://127.0.0.1:8000/admin/salles/1/edit`
2. [ ] Uploader un nouveau fichier `.glb`
3. [ ] Cliquer sur "Update Salle"
4. [ ] Vérifier que le nouveau modèle s'affiche
5. [ ] Vérifier que l'ancien fichier est toujours présent (ou supprimé selon la logique)

#### Test 4 : Salle sans modèle 3D

1. [ ] Créer une salle sans uploader de fichier 3D
2. [ ] Aller sur la page de la salle
3. [ ] Vérifier que le message "Aucun modèle 3D disponible" s'affiche
4. [ ] Vérifier qu'il n'y a pas d'erreur JavaScript

---

### 7. **Vérifications de sécurité**

- [ ] Les fichiers uploadés ont des noms uniques (pas de collision)
- [ ] Seuls les formats `.glb` et `.gltf` sont acceptés
- [ ] Les fichiers sont stockés dans un dossier public (accessible via URL)
- [ ] Le chemin en BDD commence par `/uploads/salles/`

---

### 8. **Vérifications de performance**

- [ ] Les fichiers 3D font moins de 10 MB
- [ ] Le chargement du modèle affiche un indicateur de progression
- [ ] Le modèle se charge en moins de 5 secondes (réseau local)
- [ ] Pas de ralentissement lors de la rotation du modèle

---

### 9. **Vérifications de compatibilité**

- [ ] Le modèle s'affiche sur Chrome
- [ ] Le modèle s'affiche sur Firefox
- [ ] Le modèle s'affiche sur Edge
- [ ] Le modèle s'affiche sur Safari (si disponible)
- [ ] Le modèle s'affiche sur mobile (responsive)

---

### 10. **Console du navigateur**

Ouvrir la console (F12) et vérifier :

- [ ] Pas d'erreur 404 (fichier non trouvé)
- [ ] Pas d'erreur CORS
- [ ] Message "3D Model loaded successfully" s'affiche
- [ ] Pas d'erreur Three.js

**Erreurs courantes :**
```
❌ GET http://127.0.0.1:8000/uploads/salles/abc123.glb 404 (Not Found)
   → Le fichier n'existe pas

❌ Error loading 3D model: TypeError: Cannot read property 'scene' of undefined
   → Le fichier n'est pas un modèle 3D valide

✅ 3D Model loaded successfully: /uploads/salles/abc123.glb
   → Tout fonctionne !
```

---

## 🎯 Résultat attendu

Si tous les tests passent, vous devriez avoir :

✅ Un système complet de gestion des modèles 3D  
✅ Upload de fichiers via l'admin  
✅ Stockage du chemin en BDD  
✅ Affichage dynamique avec Three.js  
✅ Contrôles souris et clavier  
✅ Responsive et adaptatif  

---

## 🐛 Dépannage rapide

### Problème : Le modèle ne s'affiche pas

**Solution 1 : Vérifier le chemin**
```bash
# Vérifier que le fichier existe
ls -la public/uploads/salles/

# Vérifier le chemin en BDD
php bin/console doctrine:query:sql "SELECT id, name, image_3d FROM salle WHERE id = 1"
```

**Solution 2 : Vérifier la console**
```
F12 → Console → Rechercher les erreurs
```

**Solution 3 : Vérifier le format**
```bash
# Vérifier que le fichier est bien un .glb
file public/uploads/salles/abc123.glb
```

---

### Problème : Erreur lors de l'upload

**Solution 1 : Vérifier les permissions**
```bash
chmod 755 public/uploads/salles/
```

**Solution 2 : Vérifier la taille du fichier**
```bash
# Vérifier la limite PHP
php -i | grep upload_max_filesize
```

**Solution 3 : Vérifier les logs Symfony**
```bash
tail -f var/log/dev.log
```

---

## 📊 Commandes utiles

### Vérifier la structure de la BDD
```bash
php bin/console doctrine:schema:validate
```

### Voir les routes
```bash
php bin/console debug:router | grep salle
```

### Vider le cache
```bash
php bin/console cache:clear
```

### Voir les logs en temps réel
```bash
tail -f var/log/dev.log
```

---

## ✅ Validation finale

Une fois tous les tests passés, vous pouvez considérer que le système est **opérationnel** ! 🎉

**Prochaines étapes :**
- [ ] Tester avec de vrais utilisateurs
- [ ] Optimiser les modèles 3D (compression)
- [ ] Ajouter des animations (optionnel)
- [ ] Ajouter un système de suppression des anciens fichiers (optionnel)

---

**Auteur** : SkillHarbor Team  
**Date** : 2026-02-07

