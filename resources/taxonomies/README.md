# Taxonomies

Static reference classifications, loaded and cached by `App\Support\Taxonomy`.

Drop the full lists here to replace the bundled subsets:

- **`google_product.txt`** — Google's official Product Taxonomy
  (https://www.google.com/basepages/producttype/taxonomy.en-US.txt).
  Format: `123 - A > B > C`, one per line.
- **`mow_boq.txt`** — Tanzania Ministry of Works bill-of-quantities elements.
  Format: `A - Preliminaries > Site establishment`, one per line.

After adding a file:

```bash
php artisan taxonomy:import
```

Without a file, a curated subset (the branches a general supplier actually
sells from, and the standard BoQ elements) keeps every form working.
