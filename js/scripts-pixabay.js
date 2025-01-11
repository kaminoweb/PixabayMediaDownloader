(function($) {
    $(document).ready(function () {
        let currentPage = 1;
        let currentQuery = '';
        let currentOrientation = '';
        let currentMinWidth = '';
        let currentMinHeight = '';

        $('#pmd-pixabay-search-form').on('submit', function (e) {
            e.preventDefault();

            currentQuery = $('#pmd-pixabay-search-query').val().trim();
            currentOrientation = $('#pmd-pixabay-orientation').val();
            currentMinWidth = $('#pmd-pixabay-min-width').val();
            currentMinHeight = $('#pmd-pixabay-min-height').val();

            if (currentQuery === '') {
                alert(__('Please enter a search query.', 'pixabay-media-downloader'));
                return;
            }

            currentPage = 1;
            searchImages();
        });

        function searchImages() {
            $('#pmd-pixabay-results').html('<div class="pmd-loading"></div>');

            $.ajax({
                url: pmd_pixabay_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'pmd_pixabay_search',
                    query: currentQuery,
                    page: currentPage,
                    orientation: currentOrientation,
                    min_width: currentMinWidth,
                    min_height: currentMinHeight,
                    nonce: pmd_pixabay_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        displayResults(response.data);
                    } else {
                        $('#pmd-pixabay-results').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        console.error('Pixabay API Error:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    $('#pmd-pixabay-results').html('<div class="notice notice-error"><p>' + __('An unexpected error occurred.', 'pixabay-media-downloader') + '</p></div>');
                    console.error('AJAX Error:', status, error);
                }
            });
        }

        function displayResults(data) {
            if (data.hits.length === 0) {
                $('#pmd-pixabay-results').html('<div class="notice notice-info"><p>' + __('No images found.', 'pixabay-media-downloader') + '</p></div>');
                return;
            }

            let html = '<div class="pmd-gallery">';
            data.hits.forEach(function (hit) {
                html += `
                    <div class="pmd-image">
                        <img src="${hit.webformatURL}" alt="${hit.tags}" />
                        <label>
                            <input type="checkbox" data-url="${hit.largeImageURL}" data-id="${hit.id}" />
                            ${__('Select', 'pixabay-media-downloader')}
                        </label>
                    </div>
                `;
            });

            html += '</div>';
            const totalPages = Math.ceil(data.totalHits / data.hits.length);
            html += '<div class="pmd-pagination">';
            if (currentPage > 1) {
                html += '<button id="pmd-prev-page" class="button">' + __('Previous', 'pixabay-media-downloader') + '</button>';
            }
            if (currentPage < totalPages) {
                html += '<button id="pmd-next-page" class="button">' + __('Next', 'pixabay-media-downloader') + '</button>';
            }
            html += '</div>';
            $('#pmd-pixabay-results').html(html);
        }

        $(document).on('click', '#pmd-next-page', function () {
            currentPage++;
            searchImages();
        });

        $(document).on('click', '#pmd-prev-page', function () {
            currentPage--;
            searchImages();
        });

        $('#pmd-pixabay-download-selected').on('click', function () {
            let selected = [];
            $('#pmd-pixabay-results input[type="checkbox"]:checked').each(function () {
                selected.push({
                    url: $(this).data('url'),
                    id: $(this).data('id')
                });
            });

            if (selected.length === 0) {
                alert(__('No images selected.', 'pixabay-media-downloader'));
                return;
            }

            $('#pmd-pixabay-download-selected').prop('disabled', true).html('<span class="dashicons dashicons-download"></span> ' + __('Downloading...', 'pixabay-media-downloader'));

            $.ajax({
                url: pmd_pixabay_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'pmd_pixabay_download_images',
                    images: selected,
                    query: currentQuery,
                    nonce: pmd_pixabay_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data); // Show success message without checkbox
                    } else {
                        alert(__('Error: ', 'pixabay-media-downloader') + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    alert(__('An unexpected error occurred.', 'pixabay-media-downloader'));
                    console.error('AJAX Error:', status, error);
                },
                complete: function () {
                    $('#pmd-pixabay-download-selected').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + __('Download Selected', 'pixabay-media-downloader'));
                }
            });
        });
    });
})(jQuery);
