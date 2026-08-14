<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mass inventory QR label layout (A4 portrait)
    |--------------------------------------------------------------------------
    |
    | Default: 5 columns × 9 rows = 45 labels / page.
    | Gaps are applied as table-cell padding (DomPDF-safe).
    | Do NOT use border-spacing — DomPDF mis-measures it and page-breaks early.
    |
    | Geometry is derived from A4 (210×297 mm). Keep margins/gaps tight but
    | printable, and keep each label content shorter than the computed cell
    | height so DomPDF does not expand rows (which causes ~2 rows/page).
    |
    */

    'columns' => (int) env('INVENTORY_LABEL_COLUMNS', 5),

    'rows' => (int) env('INVENTORY_LABEL_ROWS', 9),

    /** Page margin from paper edge (mm). Keep low to fit 9 rows. */
    'page_margin_mm' => (float) env('INVENTORY_LABEL_PAGE_MARGIN_MM', 5),

    /** Horizontal gap between labels (mm) — via cell padding. */
    'gap_x_mm' => (float) env('INVENTORY_LABEL_GAP_X_MM', 2),

    /** Vertical gap between labels (mm) — via cell padding. */
    'gap_y_mm' => (float) env('INVENTORY_LABEL_GAP_Y_MM', 1.8),

    /** Printed QR size (mm). Modest so text + full inventory number fit. */
    'qr_size_mm' => (float) env('INVENTORY_LABEL_QR_SIZE_MM', 13.5),

    /** Official logo width (mm). */
    'logo_width_mm' => (float) env('INVENTORY_LABEL_LOGO_WIDTH_MM', 10),

    /** Cap logo height (mm) — identity only, not dominant. */
    'logo_max_height_mm' => (float) env('INVENTORY_LABEL_LOGO_MAX_HEIGHT_MM', 3.2),

    /** Inner padding top / left / right inside label border (mm). */
    'label_padding_mm' => (float) env('INVENTORY_LABEL_PADDING_MM', 1.0),

    /** Space under inventory number (mm). */
    'label_padding_bottom_mm' => (float) env('INVENTORY_LABEL_PADDING_BOTTOM_MM', 0.7),

    /**
     * Official UNDIRA logo (do not rename).
     */
    'logo_path' => env('INVENTORY_LABEL_LOGO_PATH', 'images/logo-undira.png'),

    /** Body text size (pt) — legacy single-size fallback. */
    'font_size_pt' => (float) env('INVENTORY_LABEL_FONT_PT', 6.0),

    /**
     * Soft character budget for name before hard clamp (≈ 2 lines at min font).
     * Dynamic font sizing is preferred over ellipsis.
     */
    'name_max_chars' => (int) env('INVENTORY_LABEL_NAME_MAX_CHARS', 48),

    /** Safety cap for filter-scope mass print. */
    'max_items' => (int) env('INVENTORY_LABEL_MAX_ITEMS', 200),

];
