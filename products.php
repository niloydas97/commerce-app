<?php 
include_once 'config.php'; 
$page_title = "All Products - Torklub";

// --- Get filter values from URL ---
$search_term = $_GET['search'] ?? '';
$selected_category = $_GET['category'] ?? '';
$selected_vendor = $_GET['vendor'] ?? '';

// --- Fetch data for filters ---
// Fetch distinct categories for filtering
$categories_stmt = $pdo->query("SELECT name FROM product_categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct vendors (brands) for filtering
$vendors = [];
try {
    $vendors_stmt = $pdo->query("SELECT name FROM vendors ORDER BY name ASC");
    $vendors = $vendors_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // vendors table might not exist yet.
}
// --- Fetch Favicon ---
$favicon_url = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'header_favicon_url'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php if ($favicon_url): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
    <?php endif; ?>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <?php include 'header.php'; ?>

    <!-- Main Product Listing -->
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900">Our Products</h2>
            <div class="relative w-full sm:w-auto">
                <form 
                    hx-get="load_products.php" 
                    hx-target="#product-grid" 
                    hx-push-url="true" 
                    hx-indicator="#product-grid"
                    class="flex h-11"
                >
                    <input type="search" name="search" id="live-search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off" class="w-full sm:w-64 px-4 py-2 border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500">
                    <!-- Hidden fields to preserve filters when searching -->
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                    <input type="hidden" name="vendor" value="<?php echo htmlspecialchars($selected_vendor); ?>">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-r-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Search</button>
                </form>
                <div id="live-search-results" class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-b-lg shadow-lg z-10 hidden max-h-96 overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <aside class="col-span-1 lg:col-span-1 bg-white p-6 rounded-lg shadow-sm self-start">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                <form 
                    hx-get="load_products.php" 
                    hx-target="#product-grid" 
                    hx-push-url="true" 
                    hx-indicator="#product-grid"
                    id="filter-form"
                    class="space-y-6"
                >
                     <!-- Hidden field to preserve search when filtering -->
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">

                    <div>
                        <label for="category-select" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category" id="category-select" hx-get="load_products.php" hx-target="#product-grid" hx-include="[name='search'], [name='vendor']" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php if ($category === $selected_category) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="vendor-select" class="block text-sm font-medium text-gray-700">Brand</label>
                        <select name="vendor" id="vendor-select" hx-get="load_products.php" hx-target="#product-grid" hx-include="[name='search'], [name='category']" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo htmlspecialchars($vendor); ?>" <?php if ($vendor === $selected_vendor) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($vendor); ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                    </div>

                    <a href="products.php" class="text-sm font-medium text-red-600 hover:text-red-800" onclick="event.preventDefault(); document.getElementById('live-search-input').value=''; document.getElementById('category-select').value=''; document.getElementById('vendor-select').value=''; htmx.trigger('#filter-form', 'submit');">Clear All Filters</a>
                </form>
            </aside>

            <main class="col-span-1 lg:col-span-3">
                <div id="product-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6"
                    hx-get="load_products.php?search=<?php echo htmlspecialchars($search_term); ?>&category=<?php echo htmlspecialchars($selected_category); ?>&vendor=<?php echo htmlspecialchars($selected_vendor); ?>"
                    hx-trigger="load"
                    hx-swap="innerHTML"
                >
                    <!-- Products will be loaded here by HTMX -->
                    <p>Loading products...</p>
                </div>
            </main>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('live-search-input');
    const resultsContainer = document.getElementById('live-search-results');
    let debounceTimer;

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value;
        clearTimeout(debounceTimer);
        if (searchTerm.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(() => {
            htmx.ajax('GET', `live_search.php?q=${encodeURIComponent(searchTerm)}`, '#live-search-results');
            resultsContainer.style.display = 'block';
        }, 250);
    });

    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target)) {
            resultsContainer.style.display = 'none';
        }
    });

    // --- Lazy Loading ---
    const lazyLoad = (target) => {
        const io = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('opacity-100');
                    observer.unobserve(img);
                }
            });
        });
        io.observe(target);
    };

    document.body.addEventListener('htmx:afterSwap', function(event) {
        // Hide live search results after any filter/search action
        resultsContainer.style.display = 'none';

        // Re-initialize lazy loading for new content loaded by HTMX
        // This covers initial load, filtering, and "load more"
        const grid = document.getElementById('product-grid');
        const newImages = grid.querySelectorAll('.lazy');
        newImages.forEach(lazyLoad);
    });
});
</script> 
<?php include 'footer.php'; ?>
</body>
</html>