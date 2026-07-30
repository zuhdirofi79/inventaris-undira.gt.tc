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

    /** Page margin from paper edge (mm). */
    'page_margin_mm' => (float) env('INVENTORY_LABEL_PAGE_MARGIN_MM', 3),

    /** Horizontal gap between label cells (mm). */
    'gap_x_mm' => (float) env('INVENTORY_LABEL_GAP_X_MM', 2),

    /** Vertical gap between label cells (mm). */
    'gap_y_mm' => (float) env('INVENTORY_LABEL_GAP_Y_MM', 1.2),

    /** Printed QR size (mm) — largest visual element. */
    'qr_size_mm' => (float) env('INVENTORY_LABEL_QR_SIZE_MM', 17),

    /** Official logo width (mm). Target 14–18; keep smaller than QR square. */
    'logo_width_mm' => (float) env('INVENTORY_LABEL_LOGO_WIDTH_MM', 16),

    /** Cap logo height (mm) — wide logo-undira.png stays proportional. */
    'logo_max_height_mm' => (float) env('INVENTORY_LABEL_LOGO_MAX_HEIGHT_MM', 4.8),

    /**
     * Official UNDIRA logo (do not rename).
     * Absolute path resolved via public_path() for DomPDF.
     */
    'logo_path' => env('INVENTORY_LABEL_LOGO_PATH', 'images/logo-undira.png'),

    /** Body text size (pt). */
    'font_size_pt' => (float) env('INVENTORY_LABEL_FONT_PT', 7.5),

    /** Soft character limit for name before ellipsis (≈ 2 lines). */
    'name_max_chars' => (int) env('INVENTORY_LABEL_NAME_MAX_CHARS', 42),

    /** Safety cap for filter-scope mass print. */
    'max_items' => (int) env('INVENTORY_LABEL_MAX_ITEMS', 200),

];
