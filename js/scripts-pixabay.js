(function($) {
    // Ensure the script runs only after the DOM is fully loaded
    $(document).ready(function () {
        // Initialize variables to keep track of the current state
        let currentPage = 1;
        let currentQuery = '';
        let currentOrientation = '';
        let currentMinWidth = '';
        let currentMinHeight = '';

        /**
         * Handle the Pixabay search form submission
         */
        $('#pmd-pixabay-search-form').on('submit', function (e) {
            e.preventDefault(); // Prevent the default form submission behavior

            // Gather form data
            currentQuery = $('#pmd-pixabay-search-query').val().trim();
            currentOrientation = $('#pmd-pixabay-orientation').val();
            currentMinWidth = $('#pmd-pixabay-min-width').val();
            currentMinHeight = $('#pmd-pixabay-min-height').val();

            // Validate the search query
            if (currentQuery === '') {
                alert('Please enter a search query.');
                return;
            }

            // Reset to the first page for a new search
            currentPage = 1;

            // Initiate the search
            searchImages();
        });

        /**
         * Function to perform the AJAX search request to Pixabay API
         */
        function searchImages() {
            // Display a loading indicator while fetching results
            $('#pmd-pixabay-results').html('<div class="pmd-loading"></div>');

            // Make the AJAX POST request
            $.ajax({
                url: pmd_pixabay_ajax.ajax_url, // AJAX URL provided by localized script
                method: 'POST',
                data: {
                    action: 'pmd_pixabay_search', // The AJAX action hook
                    query: currentQuery,
                    page: currentPage,
                    orientation: currentOrientation,
                    min_width: currentMinWidth,
                    min_height: currentMinHeight,
                    nonce: pmd_pixabay_ajax.nonce // Security nonce
                },
                success: function (response) {
                    if (response.success) {
                        displayResults(response.data); // Display the fetched images
                    } else {
                        // Display the error message returned from the server
                        $('#pmd-pixabay-results').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        console.error('Pixabay API Error:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    // Handle unexpected errors
                    $('#pmd-pixabay-results').html('<div class="notice notice-error"><p>An unexpected error occurred.</p></div>');
                    console.error('AJAX Error:', status, error);
                }
            });
        }

        /**
         * Function to display the search results
         * @param {Object} data - The data returned from the Pixabay API
         */
        function displayResults(data) {
            // Check if any images were found
            if (data.hits.length === 0) {
                $('#pmd-pixabay-results').html('<div class="notice notice-info"><p>No images found.</p></div>');
                return;
            }

            // Initialize the HTML structure for the gallery
            let html = '<div class="pmd-gallery">';

            // Iterate over each image hit and create HTML elements
            data.hits.forEach(function (hit) {
                html += `
                    <div class="pmd-image">
                        <img src="${hit.webformatURL}" alt="${hit.tags}" />
                        <label>
                            <input type="checkbox" data-url="${hit.largeImageURL}" data-id="${hit.id}" />
                            Select
                        </label>
                    </div>
                `;
            });

            html += '</div>'; // Close the gallery div

            // Calculate the total number of pages based on total hits and hits per page
            const totalPages = Math.ceil(data.totalHits / data.hits.length);

            // Add pagination controls
            html += '<div class="pmd-pagination">';
            if (currentPage > 1) {
                html += '<button id="pmd-prev-page" class="button">Previous</button>';
            }
            if (currentPage < totalPages) {
                html += '<button id="pmd-next-page" class="button">Next</button>';
            }
            html += '</div>';

            // Inject the generated HTML into the results container
            $('#pmd-pixabay-results').html(html);
        }

        /**
         * Handle pagination button clicks
         */
        $(document).on('click', '#pmd-next-page', function () {
            currentPage++;
            searchImages(); // Fetch the next page of results
        });

        $(document).on('click', '#pmd-prev-page', function () {
            currentPage--;
            searchImages(); // Fetch the previous page of results
        });

        /**
         * Handle the download selected images button click
         */
        $('#pmd-pixabay-download-selected').on('click', function () {
            let selected = [];

            // Collect all selected images' URLs and IDs
            $('#pmd-pixabay-results input[type="checkbox"]:checked').each(function () {
                selected.push({
                    url: $(this).data('url'),
                    id: $(this).data('id')
                });
            });

            // Validate that at least one image is selected
            if (selected.length === 0) {
                alert('No images selected.');
                return;
            }

            // Confirm the download action with the user
            if ( ! confirm( `Are you sure you want to download ${selected.length} image(s)?` ) ) {
                return;
            }

            // Disable the download button to prevent multiple submissions
            $('#pmd-pixabay-download-selected').prop('disabled', true).html('<span class="dashicons dashicons-download"></span> Downloading...');

            // Make the AJAX POST request to download the selected images
            $.ajax({
                url: pmd_pixabay_ajax.ajax_url, // AJAX URL provided by localized script
                method: 'POST',
                data: {
                    action: 'pmd_pixabay_download_images', // The AJAX action hook
                    images: selected,
                    query: currentQuery, // Include the current search query for filename context
                    nonce: pmd_pixabay_ajax.nonce // Security nonce
                },
                success: function (response) {
                    if (response.success) {
                        alert('Download successful!'); // Notify the user of successful downloads
                    } else {
                        // Notify the user of any errors returned from the server
                        alert('Error: ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    // Handle unexpected errors
                    alert('An unexpected error occurred.');
                    console.error('AJAX Error:', status, error);
                },
                complete: function () {
                    // Re-enable the download button and reset its text
                    $('#pmd-pixabay-download-selected').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Download Selected');
                }
            });
        });
    });
})(jQuery);
