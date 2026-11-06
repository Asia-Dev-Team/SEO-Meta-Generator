# FreePass SEO Meta Generator

WordPress plugin that generates SEO meta tags, Open Graph and Twitter Card tags, and Schema.org JSON-LD for posts and pages, and outputs them in `<head>`.

## What it does

- On every save of a **post** or **page**, builds the tags and stores the ready HTML in the post meta field `_freepass_seo_meta_tags_html`.
- Outputs the stored HTML in `<head>` (`wp_head`, priority 1) on single posts and pages.
- On the front page, outputs Schema.org `WebSite` markup with the organization data from the settings.
- Fills values automatically when no custom value is set:
  - description — first 25 words of the content;
  - Open Graph image — the featured image;
  - Open Graph title — the post title.
- Detects the Schema.org type automatically, or uses the type chosen in the editor:
  - `Article` for posts;
  - `ContactPage` for a page with slug `contact`, `AboutPage` for slug `about`, `WebPage` for other pages;
  - available manual types: `Article`, `BlogPosting`, `WebPage`, `AboutPage`, `ContactPage`, `Service`.
- Adds list columns and Quick Edit fields for description, Open Graph title, description, image, and the auto-update switch.

## Generated tags

`meta description`, `og:title`, `og:description`, `og:image` (with width, height, type), `og:type`, `og:url`, `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, and a Schema.org JSON-LD block with a nested `Organization`.

## Requirements

- WordPress with permission to edit posts; plugin settings need `manage_options`.

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate **FreePass SEO Meta Generator** in **Plugins**.
3. Fill in the organization data in **Settings → FreePass SEO Meta Generator**.
4. Re-save existing posts and pages: tags are generated only on save.

## Settings

| Setting | Option | Fallback when empty |
|---|---|---|
| Organization name | `freepass_seo_org_name` | `FreePass Asia` |
| Organization URL | `freepass_seo_org_url` | site home URL |
| Organization logo | `freepass_seo_org_logo` | `<theme>/images/logo.png` |
| Phone | `freepass_seo_org_phone` | `+66 69 4538 8352` |
| Contact email | `freepass_seo_org_email` | — |

## Editor fields

Two meta boxes on the post and page editor:

- **Schema.org Metadata** — schema type, alternative headline, article editor, keywords, page language; for `Service`: service type, area served, price range; the **Disable metadata auto-update** checkbox.
- **Meta Tags** — meta description, Open Graph title, description, and image.

With **Disable metadata auto-update** on, saving the post does not regenerate its tags.

## Project structure

```
freepass-seo.php   The whole plugin: generation, output, list columns, Quick Edit, meta boxes, settings page
```

## Known limitations

- No `<link rel="canonical">` tag is output, although the plugin description mentions canonical URLs; only `og:url` and Schema.org `url` contain the permalink.
- The generated HTML starts with an `<html prefix="og: http://ogp.me/ns#">` tag inside `<head>`, which is invalid markup.
- `og:image:width`, `og:image:height`, and `og:image:type` are hard-coded to `1200`, `630`, and `image/png` for every image.
- `og:type` is always `article`, including for pages.
- Fallback organization data (name, phone, author URL `https://freepass.asia`) is hard-coded for FreePass Asia.
- Only `post` and `page` are supported; other post types get no tags.
- Existing content gets tags only after it is re-saved.
