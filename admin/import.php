<?php
include '../config.php';
include 'auth_check.php';

$message = '';
$error = '';
$csv_file_path = '';
$total_rows = 0;
$temp_filename = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['shopify_csv'])) {
    if ($_FILES['shopify_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed with error code: " . $_FILES['shopify_csv']['error'];
    } else {
        $upload_dir = '../images';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $temp_filename = 'import_' . uniqid() . '.csv';
        $csv_file_path = $upload_dir . '/' . $temp_filename;

        if (move_uploaded_file($_FILES['shopify_csv']['tmp_name'], $csv_file_path)) {
            try {
                $file = new SplFileObject($csv_file_path, 'r');
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
                
                $total_rows = 0;
                foreach ($file as $row) {
                    if (is_array($row) && !empty(array_filter($row))) {
                        $total_rows++;
                    }
                }
                
                $total_rows = $total_rows - 1; // -1 to skip the header row
                $file = null;
                
                if ($total_rows <= 0) {
                    $error = "CSV file is empty or has no data rows.";
                    unlink($csv_file_path);
                    $csv_file_path = '';
                } else {
                    $message = "File uploaded. Found $total_rows rows. Ready to import.";
                }
            } catch (Exception $e) {
                $error = "Could not read CSV file: " . $e->getMessage();
            }
        } else {
            $error = "Could not save uploaded file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Shopify Products</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Import Products from Shopify</h2>

        <?php if ($message): ?><div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm">

            <?php if (!$csv_file_path): ?>
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <p><strong>How to use this tool:</strong></p>
                <ol>
                    <li>In your Shopify Admin, go to <strong>Products</strong>.</li>
                    <li>Click the <strong>"Export"</strong> button.</li>
                    <li>Choose <strong>"All products"</strong> and <strong>"Plain CSV file"</strong>.</li>
                    <li>Upload that CSV file here.</li>
                </ol>
            </div>
            <form method="POST" enctype="multipart/form-data" class="mt-6">
                <div>
                    <label for="shopify_csv" class="block text-sm font-medium text-gray-700">Shopify CSV File</label>
                    <input type="file" name="shopify_csv" id="shopify_csv" accept=".csv" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <button type="submit" class="mt-4 inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700">Upload and Prepare</button>
            </form>
            
            <?php else: ?>
            
            <p>Your file is ready to be imported.</p>
            <button id="start-import-btn" class="mt-4 inline-flex justify-center rounded-md border border-transparent bg-green-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:bg-gray-400">
                Start Importing (<?php echo $total_rows; ?> rows)
            </button>
            
            <div class="mt-6 hidden" id="progress-container">
                <div class="w-full bg-gray-200 rounded-full">
                    <div class="bg-blue-600 text-xs font-medium text-blue-100 text-center p-0.5 leading-none rounded-full transition-all duration-300" id="progress-bar-inner" style="width: 0%">0%</div>
                </div>
                <div id="status-log" class="mt-2 text-sm text-gray-600">Initializing...</div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <?php if ($csv_file_path): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const startBtn = document.getElementById('start-import-btn');
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar-inner');
        const statusLog = document.getElementById('status-log');

        const BATCH_SIZE = 50;
        const TOTAL_ROWS = <?php echo $total_rows; ?>;
        const CSV_FILE = '<?php echo $temp_filename; ?>';
        
        let totalProcessed = 0;

        if (startBtn) {
            startBtn.addEventListener('click', () => {
                startBtn.disabled = true;
                startBtn.innerText = 'Importing...';
                progressContainer.style.display = 'block';
                statusLog.innerText = 'Starting import...';
                processBatch(0);
            });
        }

        async function processBatch(offset) {
            try {
                const response = await fetch(`import_batch.php?file=${CSV_FILE}&offset=${offset}&limit=${BATCH_SIZE}`);
                
                if (!response.ok) {
                    throw new Error(`Server error: ${response.statusText}`);
                }

                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                totalProcessed += data.processed_count;
                let percentage = Math.round((totalProcessed / TOTAL_ROWS) * 100);
                if (percentage > 100) percentage = 100;
                
                progressBar.style.width = `${percentage}%`;
                progressBar.innerText = `${percentage}%`;
                statusLog.textContent = `Processed ${totalProcessed} of ${TOTAL_ROWS} rows...`;

                if (totalProcessed < TOTAL_ROWS && data.processed_count > 0) {
                    processBatch(offset + BATCH_SIZE);
                } else {
                    startBtn.textContent = 'Import Complete!';
                    statusLog.textContent = `Import complete! Successfully imported ${totalProcessed} rows.`;
                    progressBar.style.width = '100%';
                    progressBar.innerText = '100%';
                    progressBar.style.background = '#28a745';
                }

            } catch (err) {
                startBtn.style.background = '#dc3545';
                startBtn.textContent = 'Import Failed';
                statusLog.innerHTML = `<span class="font-bold text-red-600">Error:</span> ${err.message}<br>Please check your server logs or try again.`;
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>