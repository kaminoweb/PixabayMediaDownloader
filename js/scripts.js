jQuery(document).ready(function ($) {
    let currentPage = 1;
    let currentQuery = '';
    let currentOrientation = '';
    let currentMinWidth = '';
    let currentMinHeight = '';

    // Handle search form submission
    $('#pmd-search-form').on('submit', function (e) {
        e.preventDefault();
        currentQuery = $('#pmd-search-query').val().trim();
        currentOrientation = $('#pmd-orientation').val();
        currentMinWidth = $('#pmd-min-width').val();
        currentMinHeight = $('#pmd-min-height').val();

        if (currentQuery === '') {
            alert('Please enter a search query.');
            return;
        }

        currentPage = 1;
        searchImages();
    });

    // Function to search images
    function searchImages() {
        $('#pmd-results').html('<div class="pmd-loading"></div>');
        $.ajax({
            url: pmd_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'pmd_search_pixabay',
                query: currentQuery,
                page: currentPage,
                orientation: currentOrientation,
                min_width: currentMinWidth,
                min_height: currentMinHeight,
                nonce: pmd_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    $('#pmd-results').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function () {
                $('#pmd-results').html('<div class="notice notice-error"><p>An unexpected error occurred.</p></div>');
            }
        });
    }

    // Function to display search results
    function displayResults(data) {
        if (data.hits.length === 0) {
            $('#pmd-results').html('<div class="notice notice-info"><p>No images found.</p></div>');
            return;
        }

        let html = '<div class="pmd-gallery">';
        data.hits.forEach(function (hit) {
            html += `
                <div class="pmd-image">
                    <img src="${hit.previewURL}" alt="${hit.tags}" />
                    <label>
                        <input type="checkbox" data-url="${hit.largeImageURL}" data-id="${hit.id}" />
                        Select
                    </label>
                </div>
            `;
        });
        html += '</div>';

        // Pagination
        const totalPages = Math.ceil(data.totalHits / 20);
        html += '<div class="pmd-pagination">';
        if (currentPage > 1) {
            html += '<button id="pmd-prev-page" class="button">Previous</button>';
        }
        if (currentPage < totalPages) {
            html += '<button id="pmd-next-page" class="button">Next</button>';
        }
        html += '</div>';

        $('#pmd-results').html(html);
    }

    // Handle pagination clicks
    $(document).on('click', '#pmd-next-page', function () {
        currentPage++;
        searchImages();
    });

    $(document).on('click', '#pmd-prev-page', function () {
        currentPage--;
        searchImages();
    });

    // Handle download button click
    $('#pmd-download-selected').on('click', function () {
        let selected = [];
        $('#pmd-results input[type="checkbox"]:checked').each(function () {
            selected.push({
                url: $(this).data('url'),
                id: $(this).data('id')
            });
        });

        if (selected.length === 0) {
            alert('No images selected.');
            return;
        }

        // Confirm action
        if (!confirm(`Are you sure you want to download ${selected.length} image(s)?`)) {
            return;
        }

        // Disable button to prevent multiple clicks
        $('#pmd-download-selected').prop('disabled', true).html('<span class="dashicons dashicons-download"></span> Downloading...');

        $.ajax({
            url: pmd_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'pmd_download_images',
                images: selected,
                query: currentQuery, // Include the current search query
                nonce: pmd_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    // Optionally, you can refresh the media library or perform other actions
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('An unexpected error occurred.');
            },
            complete: function () {
                $('#pmd-download-selected').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Download Selected');
            }
        });
    });
});

