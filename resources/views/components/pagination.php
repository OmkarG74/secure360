<?php
/**
 * Reusable Standardized Pagination Component (Reference: Attendance Page)
 * 
 * Parameters:
 * - $currentPage (int, default: 1)
 * - $totalRecords (int, default: 0)
 * - $pageSize (int, default: 10)
 * - $queryParams (array, optional query parameters to preserve)
 * - $baseUrl (string, optional base URL)
 */

$currentPage = max(1, (int)($currentPage ?? 1));
$pageSize = max(1, (int)($pageSize ?? 10));
$totalRecords = max(0, (int)($totalRecords ?? 0));
$totalPages = max(1, (int)ceil($totalRecords / $pageSize));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$startRecord = $totalRecords > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$endRecord = min($currentPage * $pageSize, $totalRecords);

$queryParams = $queryParams ?? $_GET ?? [];
$baseUrl = $baseUrl ?? (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

if (!function_exists('secure360_page_url')) {
    function secure360_page_url(string $baseUrl, array $params, int $page): string {
        $params['page'] = $page;
        return $baseUrl . '?' . http_build_query($params);
    }
}

// Calculate 5-page display window centered around active page
$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $startPage + 4);
if ($endPage - $startPage < 4) {
    $startPage = max(1, $endPage - 4);
}
?>

<div class="table-footer-pagination">
    <div class="pagination-info">
        Showing <?= $startRecord ?> to <?= $endRecord ?> of <?= $totalRecords ?> records
    </div>

    <div class="pagination-controls">
        <!-- Previous Button -->
        <?php if ($currentPage > 1): ?>
            <a href="<?= e(secure360_page_url($baseUrl, $queryParams, $currentPage - 1)) ?>" class="page-btn" title="Previous Page">
                &lsaquo;
            </a>
        <?php else: ?>
            <button type="button" class="page-btn" disabled title="Previous Page">
                &lsaquo;
            </button>
        <?php endif; ?>

        <!-- Numeric Page Buttons -->
        <?php if ($totalRecords > 0): ?>
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <?php if ($p === $currentPage): ?>
                    <button type="button" class="page-btn active">
                        <?= $p ?>
                    </button>
                <?php else: ?>
                    <a href="<?= e(secure360_page_url($baseUrl, $queryParams, $p)) ?>" class="page-btn">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        <?php else: ?>
            <button type="button" class="page-btn active">1</button>
        <?php endif; ?>

        <!-- Next Button -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= e(secure360_page_url($baseUrl, $queryParams, $currentPage + 1)) ?>" class="page-btn" title="Next Page">
                &rsaquo;
            </a>
        <?php else: ?>
            <button type="button" class="page-btn" disabled title="Next Page">
                &rsaquo;
            </button>
        <?php endif; ?>
    </div>
</div>
