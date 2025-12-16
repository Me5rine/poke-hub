# model.md

Tu travailles sur un **plugin WordPress modulaire**.  
Toutes les générations de code, de documentation et de structure doivent suivre les conventions suivantes.

---

## 🎯 Style général
- Langue : **français**
- Ton : **concis, professionnel et clair**
- Commentaires : précis, utiles, sans redondance
- Chaines de traduction en anglais pour les termes affichés en front

---

## ⚙️ Organisation du code
- Convention de nommage : **snake_case**
- Préférer l’immuabilité des variables (`const` / `final` / `readonly` quand possible)
- Utiliser des **annotations de type** explicites
- Les fonctions doivent rester **courtes (≤ 50 lignes)** et cohérentes
- Respecter les **WordPress Coding Standards (PHPCS)** pour PHP
- Pour JS/TS : syntaxe moderne, pas de dépendances inutiles

---

## 📘 Documentation WordPress
Quand tu génères la documentation du plugin :

- Produis un fichier `readme.txt` compatible avec le *WordPress Plugin Directory*
- Utilise le format standard attendu :
  - `=== Nom du plugin ===`
  - `Contributors:`
  - `Tags:`
  - `Requires at least:`
  - `Tested up to:`
  - `Stable tag:`
  - `License:`
  - `License URI:`
  - `== Description ==`
  - `== Installation ==`
  - `== Frequently Asked Questions ==`
  - `== Screenshots ==`
  - `== Changelog ==`
- Rédige en français clair et neutre
- Mets en avant les fonctionnalités, la compatibilité et les cas d’usage
- N’ajoute pas de fioritures Markdown non compatibles avec WordPress

---

## 🎨 Structure et gestion du CSS
- Chaque **module, composant ou page** doit avoir son propre fichier `.css` ou `.scss`
- **Aucun style inline** dans le HTML, PHP, JSX ou TSX  
  sauf si :
  - le style doit être **calculé dynamiquement** (ex. hauteur dépendant d’un script JS)
  - ou si l’environnement ne permet pas de charger du CSS externe (ex. email HTML)
- Nommer les fichiers CSS selon le module :
  - `header/header.css`
  - `footer/footer.css`
  - `user-profile/user-profile.css`
- Les sélecteurs doivent être **scopés** au module (`.user-profile .avatar`)
- Ne pas utiliser de styles globaux sauf dans `global.css` (reset, variables, typographie)
- Importer explicitement les fichiers CSS dédiés dans le module correspondant
- Si un style inline est absolument nécessaire, **documente la raison** dans un commentaire au-dessus

---

## 🧱 Structure du dépôt
