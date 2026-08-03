<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mass inventory QR label layout (A4 portrait)
    |--------------------------------------------------------------------------
    |
    | Default: 4 columns × 10 rows = 40 labels / page.
    | Horizontal label: logo + text left, larger QR right, inventory number bottom.
    |
    | Gaps are maximized for cutter spacing while keeping QR scannable.
    | (True 8–10mm gaps conflict with 20mm+ QR at 10 rows on A4.)
    |
    */

    'columns' => (int) env('INVENTORY_LABEL_COLUMNS', 4),

    'rows' => (int) env('INVENTORY_LABEL_ROWS', 10),

    /** Page margin from paper edge (mm). */
    'page_margin_mm' => (float) env('INVENTORY_LABEL_PAGE_MARGIN_MM', 8),

    /** Horizontal gap between label cells (mm) — cutting gutter. */
    'gap_x_mm' => (float) env('INVENTORY_LABEL_GAP_X_MM', 5),

    /** Vertical gap between label cells (mm) — cutting gutter. */
    'gap_y_mm' => (float) env('INVENTORY_LABEL_GAP_Y_MM', 4),

    /** Printed QR size (mm) — primary focus, larger for easier scanning. */
    'qr_size_mm' => (float) env('INVENTORY_LABEL_QR_SIZE_MM', 20),

    /** Official logo width (mm). Keep smaller than QR square. */
    'logo_width_mm' => (float) env('INVENTORY_LABEL_LOGO_WIDTH_MM', 15),

    /** Cap logo height (mm) — wide logo-undira.png stays proportional. */
    'logo_max_height_mm' => (float) env('INVENTORY_LABEL_LOGO_MAX_HEIGHT_MM', 5),

    /** Inner padding inside each label border (mm). */
    'label_padding_mm' => (float) env('INVENTORY_LABEL_PADDING_MM', 2),

    /**
     * Official UNDIRA logo (do not rename).
     * Absolute path resolved via public_path() for DomPDF.
     */
    'logo_path' => env('INVENTORY_LABEL_LOGO_PATH', 'images/logo-undira.png'),

    /** Body text size (pt) — legacy single-size fallback. */
    'font_size_pt' => (float) env('INVENTORY_LABEL_FONT_PT', 7.5),

    /** Soft character limit for name before ellipsis (≈ 2 lines). */
    'name_max_chars' => (int) env('INVENTORY_LABEL_NAME_MAX_CHARS', 36),

    /** Safety cap for filter-scope mass print. */
    'max_items' => (int) env('INVENTORY_LABEL_MAX_ITEMS', 200),

];
