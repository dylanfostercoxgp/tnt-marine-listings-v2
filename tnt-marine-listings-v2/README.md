# TNT Marine Listings
**WordPress Plugin — Created by Cox Group**
Version 1.0.0

---

## Installation

1. Upload the `tnt-marine-listings` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins > Installed Plugins** in WordPress admin
3. A **Marine Listings** item will appear in the left admin menu

---

## Adding Listings

1. Go to **Marine Listings > Add New Listing**
2. Enter the listing **title** (e.g. "2025 Nor-Tech 390 Sport Center Console | 39ft")
3. Fill in the meta boxes:
   - **Listing Status** — Check "Mark as Sold" to hide the listing from the site
   - **Overview** — Price, Year, Length, Location, Class, Model, Hours, Capacity
   - **Measurements & Weights** — LOA, Beam, Dry Weight, Fuel Tanks
   - **Propulsion** — Select number of engines, then fill in make/model/power/hours/fuel for each
   - **Features & Description** — Description (HTML allowed), and one-per-line feature lists for Power/Tech, Cockpit/Deck, Cabin/Interior, Trailer/Accessories, and Bonus notes
   - **Photo Gallery** — Click "Add / Edit Photos" to open the media library and select multiple images. The first image will be used as the card thumbnail. Remove photos with the red X.
4. Click **Publish**

---

## Displaying Listings

### Archive Page (Recommended)
The plugin registers a built-in archive at `/listings/`. After activating, go to **Settings > Permalinks** and click **Save Changes** to flush rewrite rules. Then visit `https://tntcustommarine.com/listings/`.

### Shortcode
Paste this shortcode on any page to display the listing grid:

```
[marine_listings]
```

Optional attribute to change listings per page:
```
[marine_listings per_page="9"]
```

---

## Sorting
The listings page includes a Sort By dropdown with:
- Newest Listed (default)
- Price: Low to High / High to Low
- Year: Newest First / Oldest First
- Length: Shortest First / Longest First

---

## Inquiry Form
Each single listing page includes a built-in inquiry form. Submissions are emailed to the site's **admin email address** (set under **Settings > General**). To change the recipient email, update the admin email or modify `includes/inquiry-form.php`.

---

## Sold Listings
Check "Mark as Sold" in the Listing Status meta box on any listing. Sold listings are completely hidden from the grid and archive — they do not display a badge, they simply do not appear.

---

## Customizing Styles
All frontend styles are in `assets/css/tnt-marine.css`. Brand colors are controlled via CSS variables at the top of that file:

```css
--tnt-primary:  #1a2e4a;   /* Navy - headings, panel background */
--tnt-accent:   #c8922a;   /* Gold - price, CTA buttons, bullet dots */
```

Change these two values to match any brand palette.

---

## File Structure

```
tnt-marine-listings/
├── tnt-marine-listings.php     # Main plugin file
├── includes/
│   ├── post-type.php           # Custom post type registration
│   ├── meta-boxes.php          # All admin meta fields + save logic
│   ├── shortcodes.php          # [marine_listings] shortcode
│   ├── inquiry-form.php        # Form render + AJAX handler
│   └── template-loader.php     # Loads single listing template
├── templates/
│   └── single-marine_listing.php  # Single listing page template
└── assets/
    ├── css/
    │   ├── tnt-marine.css       # Frontend styles
    │   └── tnt-marine-admin.css # Admin styles
    └── js/
        ├── tnt-marine.js        # Gallery, accordion, inquiry AJAX
        └── tnt-marine-admin.js  # Gallery uploader, engine toggle
```

---

## Requirements
- WordPress 5.8+
- PHP 7.4+

---

*Plugin created by Cox Group for TNT Custom Marine*
