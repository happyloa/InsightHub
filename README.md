# InsightHub – Site Analytics Dashboard

## Overview
InsightHub is a lightweight WordPress analytics dashboard that surfaces key site statistics directly inside wp-admin and through a reusable shortcode. The plugin ships with an updated card-based layout and quick-read typography so administrators can review performance at a glance.

- **Current version:** 1.0.0
- **Minimum requirements:** WordPress 5.8+, PHP 7.4+

## Installation & Activation
1. Download or clone the plugin into your WordPress `wp-content/plugins/insighthub/` directory.
2. In the WordPress admin area, navigate to **Plugins → Installed Plugins**.
3. Activate **InsightHub – Site Analytics Dashboard**.
4. Visit **Dashboard → InsightHub** to view the analytics cards.

## Shortcodes
- `[insighthub_stats]` – Renders a compact stats box with total posts, comments, and users. Place this shortcode in any post, page, or widget to surface the latest site totals on the front end.

## Admin Dashboard Features
The refreshed admin screen (Dashboard → InsightHub) includes:
- **Totals grid:** Cards for published posts, pages, comments, users, categories, and tags, arranged in a responsive grid.
- **Recent activity:** Quick counts for posts and comments over the last 7 and 30 days.
- **Post type coverage:** A table summarizing publish counts across built-in and custom post types.
- **WooCommerce snapshot:** Optional card showing 30-day order and sales totals when WooCommerce access is available.
- **Polished UI:** Light, card-based styling, subtle shadows, and consistent spacing for faster scanning after the redesign.

## Marketing Tool Integrations
Planned and expandable integrations to consolidate marketing analytics:
- **Google Site Kit** (planned): connect to pull Search Console and Analytics highlights.
- **ActiveCampaign** (planned): sync campaign engagement summaries.
- **Microsoft Clarity** (planned): display recent session recordings and heatmap trends.

### Authentication (when integrations are enabled)
- Each integration will add a dedicated **Connect** button within the InsightHub dashboard or settings panel.
- Clicking **Connect** will launch the provider’s OAuth or API key prompt; once approved, InsightHub will store tokens securely using WordPress options and display a success badge on the card.
- A **Reconnect** or **Disconnect** link will appear on each integration card to refresh or revoke access.

## Admin UI Preview
The redesigned admin dashboard uses a grid of white cards with border accents, rounded corners, and subtle shadows. Primary metrics are emphasized with large numeric styles, while recent activity and WooCommerce panels include concise lists for quick scanning. Tables adopt the standard WordPress striped styling for readability on data-dense rows.
