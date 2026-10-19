<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }

/**
 * Pagination helper
 * 
 * @param int    $totalItems   Total rows in DB
 * @param int    $currentPage  Current page (from $_GET['page'])
 * @param int    $perPage      Rows per page
 * @param string $baseUrl      URL without page param (e.g. "admin/users.php?role=vendor")
 * @return array ['offset', 'totalPages', 'page', 'html']
 */
function paginate(int $totalItems, int $currentPage, int $perPage = 20, string $baseUrl = ''): array
{
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $page = max(1, min($currentPage, $totalPages));
    $offset = ($page - 1) * $perPage;

    // Build URL properly
    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

    $html = '';
    if ($totalPages > 1) {
        $html .= '<nav class="mt-3"><ul class="pagination justify-content-center flex-wrap">';

        // Previous
        if ($page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . $separator . 'page=' . ($page - 1)) . '">← Prev</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">← Prev</span></li>';
        }

        // Page numbers (max 7 visible)
        $start = max(1, $page - 3);
        $end   = min($totalPages, $page + 3);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . $separator . 'page=1') . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $page) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . e($baseUrl . $separator . 'page=' . $i) . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . $separator . 'page=' . $totalPages) . '">' . $totalPages . '</a></li>';
        }

        // Next
        if ($page < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . $separator . 'page=' . ($page + 1)) . '">Next →</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next →</span></li>';
        }

        $html .= '</ul></nav>';

        // Info line
        $from = $offset + 1;
        $to = min($offset + $perPage, $totalItems);
        $html .= '<div class="text-center text-muted small mt-2">Showing ' . $from . '–' . $to . ' of ' . $totalItems . '</div>';
    }

    return [
        'offset'     => $offset,
        'totalPages' => $totalPages,
        'page'       => $page,
        'perPage'    => $perPage,
        'html'       => $html,
    ];
}

/**
 * Get current page number from URL
 */
function current_page_num(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}