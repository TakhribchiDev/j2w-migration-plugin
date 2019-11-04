window.addEventListener("load", function() {
    // Form submit
    let form = document.getElementById("phoca-migration-ajax-form");

    if (!form) return;

    form.addEventListener("submit", function(event) {

        // Prevent the form from submitting the normal way
        event.preventDefault();

        // Define the action that is hooked to the request send
        let data = {
            action: "phoca_migrate"
        };

        let checkboxes = [
            'phoca_migrate_categories',
            'phoca_migrate_posts',
            'phoca_delete_thumbs_resized',
            'phoca_empty_posts_categories',
            'phoca_fix_categories_parents'
        ];

        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxIsCheckedByName(checkboxes[i])) {
                data[checkboxes[i]] = 1;
            }
        }

        if (data['phoca_migrate_categories'] ||
            data['phoca_delete_thumbs_resized'] ||
            data['phoca_empty_posts_categories'] ||
            data['phoca_fix_categories_parents']) {
            let xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    // Alert if request completed successfully
                    alert("Request finished with the code: " + this.responseText);
                }
            };

            xmlhttp.open('post', phoca_ajax_obj.ajax_url, true);
            xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            let encoded_data = encodePostData(data);
            xmlhttp.send(encoded_data);
        }

        if (data['phoca_migrate_posts']) {
            // Show the progress bar
            let progress = document.getElementById('phoca-progress');
            progress.removeAttribute('hidden');

            // XML Http Request for each post_range posts
            let posts_num = Number(phoca_ajax_obj['post_count']);
            let cursor = 0;
            let migrated_posts = 0;
            let post_range = 100;

            // Define the range of posts to be migrated for the first request
            data['first_id'] = cursor + 1;
            data['last_id'] = posts_num - cursor < post_range ? posts_num : cursor + post_range;
            cursor += post_range;

            console.log(cursor);
            console.log(data);

            let xmlhttp = new XMLHttpRequest();
            xmlhttp.timeout = 180000;
            xmlhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    // Show the progress on the progressbar
                    let progressbar = document.getElementById('phoca-progressbar');
                    migrated_posts += (data['last_id'] - data['first_id']);
                    let migrated_fraction = Math.round((migrated_posts / posts_num) * 100);
                    progressbar.innerHTML = migrated_fraction + '%';
                    progressbar.style.width = migrated_fraction + '%';

                    // Stop making requests if migration of posts completed
                    if (cursor >= posts_num) return;

                    // Change the cursor value and define the range of posts to be migrated
                    data['first_id'] = cursor + 1;
                    data['last_id'] = posts_num - cursor < post_range ? posts_num : cursor + post_range;
                    cursor += post_range;

                    console.log(cursor);
                    console.log(data);

                    // Send another request just after the previous one completed
                    this.open('post', phoca_ajax_obj.ajax_url, true);
                    this.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    let encoded_data = encodePostData(data);
                    this.send(encoded_data);
                }

                if (this.readyState == 4 && (this.status == 503 || this.status == 500 || this.status == 502)) {
                    setTimeout(function () {
                        console.log('5xx error, rollback and code rerun after 60 seconds');
                    }, 60000);

                    let post_data = {
                        rollback_posts: '1'
                    };
                    Object.assign(post_data, data);

                    console.log(cursor);
                    console.log(post_data);

                    // Send another request just after the previous one completed
                    this.open('post', phoca_ajax_obj.ajax_url, true);
                    this.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    let encoded_data = encodePostData(post_data);
                    this.send(encoded_data);
                }
            };

            xmlhttp.ontimeout = function () {
                setTimeout(function () {
                    console.log('5xx error, rollback and code rerun after 60 seconds');
                }, 60000);

                let post_data = {
                    rollback_posts: '1'
                };
                Object.assign(post_data, data);

                console.log(cursor);
                console.log(post_data);

                // Send another request just after the previous one completed
                this.open('post', phoca_ajax_obj.ajax_url, true);
                this.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                let encoded_data = encodePostData(post_data);
                this.send(encoded_data);
            };

            xmlhttp.onerror = function () {
                setTimeout(function () {
                    console.log('5xx error, rollback and code rerun after 60 seconds');
                }, 60000);

                let post_data = {
                    rollback_posts: '1'
                };
                Object.assign(post_data, data);

                console.log(cursor);
                console.log(post_data);

                // Send another request just after the previous one completed
                this.open('post', phoca_ajax_obj.ajax_url, true);
                this.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                let encoded_data = encodePostData(post_data);
                this.send(encoded_data);
            };

            xmlhttp.open('post', phoca_ajax_obj.ajax_url, true);
            xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            let encoded_data = encodePostData(data);
            xmlhttp.send(encoded_data);
        }

        // Url encode the data to be sent with post request
        function encodePostData(data) {
            return Object.keys(data).map(
                function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }
            ).join('&');
        }

        // Check if the checkbox is checked or not by using its name
        function checkboxIsCheckedByName(checkbox_name) {
            let checkbox = document.querySelector('input[type=checkbox][name=' + checkbox_name + ']');

            return checkbox.checked;
        }
    });

    console.log(phoca_ajax_obj);
});