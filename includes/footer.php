</main>
    
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Page</title>
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1 0 auto;
        }
        footer {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <main>
        <!-- Your page content goes here -->
    </main>

    <footer class="bg-gray-900 text-gray-100 py-12">
        <div class="container mx-auto px-6">
            <div class="flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <!-- Main footer content -->
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-semibold tracking-tight mb-4">CHM Tracking System</h3>
                        <p class="text-gray-300 max-w-2xl mx-auto leading-relaxed">
                            Comprehensive tracking solution for Batch 2025-2026. Streamlining operations and enhancing productivity.
                        </p>
                    </div>
                    
                    <!-- Bottom section with copyright and version -->
                    <div class="border-t border-gray-700 pt-8 flex flex-col md:flex-row justify-between items-center">
                        <!-- Copyright (left side) -->
                        <p class="text-gray-400 text-sm md:text-base order-2 md:order-1 mt-4 md:mt-0">
                            &copy; <?= date('Y') ?> CHM Tracking System / Cabaloquimiralabmanja / Batch 2025-2026. All rights reserved.
                        </p>
                        
                        <!-- Version (right side) -->
                        <span class="text-gray-500 text-xs order-1 md:order-2">
                            Version 1.0
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
</body>
</html>