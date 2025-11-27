# Pilanto Theme Integration

## Custom H1 Field for Location Pages

### What is it?
The plugin creates a custom meta field `_pilanto_page_h1` that contains SEO-friendly H1 titles for location pages (continents, countries, cities).

### Field Values

**Post Title (used in navigation, breadcrumbs, H2 headings):**
- `Europa`
- `Danmark` 
- `København`

**Custom H1 Field `_pilanto_page_h1` (displayed as page H1):**
- `Hvad er klokken i Europa? Tidszoner og aktuel tid`
- `Hvad er klokken i Danmark? Tidszoner og aktuel tid`
- `Hvad er klokken i København? Aktuel tid`

---

## How to Use in Theme

### Option 1: In your main template file (single.php or page.php)

```php
<?php
// Get custom H1 if it exists, otherwise use post title
$h1_title = get_post_meta( get_the_ID(), '_pilanto_page_h1', true );
if ( empty( $h1_title ) ) {
    $h1_title = get_the_title();
}
?>

<h1 class="entry-title"><?php echo esc_html( $h1_title ); ?></h1>
```

### Option 2: In header.php or hero section

```php
<?php
$custom_h1 = get_post_meta( get_the_ID(), '_pilanto_page_h1', true );
if ( $custom_h1 ) {
    echo '<h1 class="page-title">' . esc_html( $custom_h1 ) . '</h1>';
} else {
    the_title( '<h1 class="page-title">', '</h1>' );
}
?>
```

### Option 3: Add to functions.php as a helper function

```php
/**
 * Get page H1 title (custom or default)
 */
function pilanto_get_h1_title( $post_id = null ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    
    // Try custom H1 field first
    $custom_h1 = get_post_meta( $post_id, '_pilanto_page_h1', true );
    
    if ( ! empty( $custom_h1 ) ) {
        return $custom_h1;
    }
    
    // Fallback to post title
    return get_the_title( $post_id );
}
```

Then use in templates:
```php
<h1><?php echo esc_html( pilanto_get_h1_title() ); ?></h1>
```

---

## Testing

1. Upload plugin version 2.8.3+
2. Import locations (continents, countries, cities)
3. View a continent page (e.g., Europa)
4. You should see:
   - **H1**: "Hvad er klokken i Europa? Tidszoner og aktuel tid"
   - **Post Title in admin**: "Europa"
   - **Breadcrumb**: "Europa"
   - **H2 headings**: "Tidszoner i Europa", etc.

---

## Database Structure

**Meta Key:** `_pilanto_page_h1`
**Meta Value:** SEO-friendly H1 title
**Post Types:** `wta_location` (continents, countries, cities)

### Example Query
```sql
SELECT p.post_title, pm.meta_value as custom_h1
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_pilanto_page_h1'
WHERE p.post_type = 'wta_location'
AND p.post_status = 'publish'
ORDER BY p.post_title;
```

---

## Advantages

✅ **SEO**: H1 optimized for search queries like "hvad er klokken i Europa"
✅ **Navigation**: Clean post titles in menus and breadcrumbs
✅ **Consistency**: Programmatically generated, consistent format
✅ **Flexibility**: Easy to change template in plugin without theme changes
✅ **Yoast Compatible**: Also saved as `_yoast_wpseo_title`

