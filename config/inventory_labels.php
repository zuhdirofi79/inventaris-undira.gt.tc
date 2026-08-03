<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mass inventory QR label layout (A4 portrait)
    |--------------------------------------------------------------------------
    |
    | Default: 5 columns × 8 rows = 40 labels / page.
    | Gaps are applied as table-cell padding (DomPDF-safe).
    | Do NOT use border-spacing — DomPDF mis-measures it and page-breaks early.
    |
    */

    'columns' => (int) env('INVENTORY_LABEL_COLUMNS', 5),

    'rows' => (int) env('INVENTORY_LABEL_ROWS', 8),

    /** Page margin from paper edge (mm). */
    'page_margin_mm' => (float) env('INVENTORY_LABEL_PAGE_MARGIN_MM', 5),

    /** Horizontal gap between labels (mm) — via cell padding. */
    'gap_x_mm' => (float) env('INVENTORY_LABEL_GAP_X_MM', 5),

    /** Vertical gap between labels (mm) — via cell padding. */
    'gap_y_mm' => (float) env('INVENTORY_LABEL_GAP_Y_MM', 5),

    /** Printed QR size (mm) — locked. */
    'qr_size_mm' => (float) env('INVENTORY_LABEL_QR_SIZE_MM', 20),

    /** Official logo width (mm). */
    'logo_width_mm' => (float) env('INVENTORY_LABEL_LOGO_WIDTH_MM', 14),

    /** Cap logo height (mm). */
    'logo_max_height_mm' => (float) env('INVENTORY_LABEL_LOGO_MAX_HEIGHT_MM', 4.5),

    /** Inner padding top / left / right inside label border (mm). */
    'label_padding_mm' => (float) env('INVENTORY_LABEL_PADDING_MM', 1.0),

    /** Extra empty space under inventory number (mm). */
    'label_padding_bottom_mm' => (float) env('INVENTORY_LABEL_PADDING_BOTTOM_MM', 2.5),

    /**
     * Official UNDIRA logo (do not rename).
     */
    'logo_path' => env('INVENTORY_LABEL_LOGO_PATH', 'images/logo-undira.png'),

    /** Body text size (pt) — legacy single-size fallback. */
    'font_size_pt' => (float) env('INVENTORY_LABEL_FONT_PT', 7.5),

    /** Soft character limit for name before ellipsis (≈ 2 lines). */
    'name_max_chars' => (int) env('INVENTORY_LABEL_NAME_MAX_CHARS', 32),

    /** Safety cap for filter-scope mass print. */
    'max_items' => (int) env('INVENTORY_LABEL_MAX_ITEMS', 200),

];
