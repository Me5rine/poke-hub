# 🚀 Quick Start - Créer un nouveau bloc

Guide rapide pour créer un nouveau bloc dans le module Blocks.

## Option 1 : Bloc PHP simple (5 minutes)

### 1. Créer le dossier

```bash
mkdir -p modules/blocks/blocks/mon-bloc
cd modules/blocks/blocks/mon-bloc
```

### 2. Créer `block.json`

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 2,
	"name": "pokehub/mon-bloc",
	"title": "Mon Bloc",
	"category": "pokehub",
	"icon": "star-filled",
	"description": "Description du bloc",
	"textdomain": "poke-hub",
	"supports": {
		"html": false
	},
	"attributes": {
		"texte": {
			"type": "string",
			"default": "Hello World"
		}
	},
	"render": "file:./render.php"
}
```

### 3. Créer `render.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

$texte = $attributes['texte'] ?? 'Hello World';
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <p><?php echo esc_html($texte); ?></p>
</div>
```

### 4. Enregistrer dans `blocks-register.php`

```php
'mon-bloc' => [
    'path' => POKE_HUB_BLOCKS_PATH . '/blocks/mon-bloc',
    'has_js' => false,
],
```

### 5. Activer le module Blocks

Allez dans **Poké HUB → Settings → General** et cochez **Blocks**.

✅ **C'est tout !** Votre bloc est prêt.

## Option 2 : Bloc JavaScript/React (15 minutes)

### 1. Créer avec create-block

```bash
cd modules/blocks/blocks/
npx @wordpress/create-block@latest mon-bloc-avance --variant=dynamic
cd mon-bloc-avance
```

### 2. Modifier `src/block.json`

Changez `name` en `pokehub/mon-bloc-avance` et `category` en `pokehub`.

### 3. Modifier `src/edit.js`

```javascript
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const { texte } = attributes;
    const blockProps = useBlockProps();

    return (
        <>
            <InspectorControls>
                <PanelBody title="Paramètres">
                    <TextControl
                        label="Texte"
                        value={texte || ''}
                        onChange={(value) => setAttributes({ texte: value })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <p>{texte || 'Hello World'}</p>
            </div>
        </>
    );
}
```

### 4. Modifier `src/render.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

$texte = $attributes['texte'] ?? 'Hello World';
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <p><?php echo esc_html($texte); ?></p>
</div>
```

### 5. Compiler

```bash
npm run build
```

### 6. Enregistrer dans `blocks-register.php`

```php
'mon-bloc-avance' => [
    'path' => POKE_HUB_BLOCKS_PATH . '/blocks/mon-bloc-avance',
    'has_js' => true,
],
```

✅ **Votre bloc avancé est prêt !**

## 📚 Documentation complète

- [README.md](../README.md) - Documentation complète
- [BLOCK_TYPES.md](./BLOCK_TYPES.md) - Comparaison des approches
- [Tutoriel WordPress](https://developer.wordpress.org/block-editor/getting-started/create-block/) - Tutoriel officiel


