<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mass inventory QR label layout (A4 portrait)
    |--------------------------------------------------------------------------
    |
    | Default: 3 columns × 10 rows = 30 labels / page.
    | To switch density, change columns/rows only, e.g.:
    |   4 × 9  = 36 labels / page
    |   4 × 10 = 40 labels / page
    |
    */

    'columns' => (int) env('INVENTORY_LABEL_COLUMNS', 3),

    'rows' => (int) env('INVENTORY_LABEL_ROWS', 10),

    /** Page margin from paper edge (mm). Tight enough for 20mm QR + header. */
    'page_margin_mm' => (float) env('INVENTORY_LABEL_PAGE_MARGIN_MM', 3.5),

    /** Horizontal gap between label cells (mm). Target 2–3 mm. */
    'gap_x_mm' => (float) env('INVENTORY_LABEL_GAP_X_MM', 2.2),

    /** Vertical gap between label cells (mm). Target 2–3 mm. */
    'gap_y_mm' => (float) env('INVENTORY_LABEL_GAP_Y_MM', 1.5),

    /** Printed QR size (mm). Minimum 20 for phone-camera scan. */
    'qr_size_mm' => (float) env('INVENTORY_LABEL_QR_SIZE_MM', 20),

    /** Official UNDIRA logo width (mm). Keep smaller than QR (10–15). */
    'logo_width_mm' => (float) env('INVENTORY_LABEL_LOGO_WIDTH_MM', 12),

    /** Cap logo height so header stays compact (mm). */
    'logo_max_height_mm' => (float) env('INVENTORY_LABEL_LOGO_MAX_HEIGHT_MM', 4.5),

    /** Relative path under public/ for the institutional logo. */
    'logo_path' => env('INVENTORY_LABEL_LOGO_PATH', 'images/undira.png'),

    /** Body text size (pt). Target 8–9. */
    'font_size_pt' => (float) env('INVENTORY_LABEL_FONT_PT', 8),

    /** Soft character limit for name before ellipsis (≈ 2 lines). */
    'name_max_chars' => (int) env('INVENTORY_LABEL_NAME_MAX_CHARS', 48),

    /** Safety cap for filter-scope mass print. */
    'max_items' => (int) env('INVENTORY_LABEL_MAX_ITEMS', 500),

];
