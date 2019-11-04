window.addEventListener("load", function() {
    // Form submit
    let form = document.getElementById("k2-migration-ajax-form");

    if (!form) return;

    form.addEventListener("submit", function(event) {

        // Prevent the form from submitting the normal way
        event.preventDefault();

        // Define the action that is hooked to the request send
        let data = {
            action: "k2_migrate"
        };

        let checkboxes = [
            'k2_migrate_categories',
            'k2_migrate_posts',
            'k2_empty_posts_categories',
            'k2_migrate_extra_fields'
        ];

        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxIsCheckedByName(checkboxes[i])) {
                data[checkboxes[i]] = 1;
            }
        }

        if (data['k2_migrate_categories'] ||
            data['k2_empty_posts_categories'] ||
            data['k2_migrate_extra_fields']) {

            let xmlhttp = new XMLHttpRequest();
            console.log('Request Created!');
            xmlhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    // Alert if request completed successfully
                    console.log(this.responseText);
                    alert("Request finished with the code: " + this.responseText);
                }
            };

            xmlhttp.open('post', k2_ajax_obj.ajax_url, true);
            xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            let encoded_data = encodePostData(data);
            xmlhttp.send(encoded_data);
        }

        if (data['k2_migrate_posts']) {
            // Show the progress bar
            let progress = document.getElementById('k2-progress');
            progress.removeAttribute('hidden');

            // XML Http Request for each post_range posts
            let sorted_posts_ids = k2_ajax_obj.posts_ids.sort(function (a, b) { return a - b });
            let cursor = 0;
            let migrated_posts = 0;
            let post_range = 100;

            // Define the range of posts to be migrated for the first request
            let ids_to_send = sorted_posts_ids.slice(cursor, cursor + post_range);
            data['posts_ids'] = JSON.stringify(ids_to_send);
            cursor += post_range;

            console.log(cursor);
            console.log(data);

            let xmlhttp = new XMLHttpRequest();
            xmlhttp.timeout = 180000;
            xmlhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    // Show the progress on the progressbar
                    let progressbar = document.getElementById('k2-progressbar');
                    migrated_posts += ids_to_send.length;
                    let migrated_fraction = Math.round((migrated_posts / sorted_posts_ids.length) * 100);
                    progressbar.innerHTML = migrated_fraction + '%';
                    progressbar.style.width = migrated_fraction + '%';

                   console.log(this.responseText);

                    // Stop making requests if migration of posts completed
                    if (cursor >= sorted_posts_ids.length) return;

                    // Change the cursor value and define the range of posts to be migrated
                    ids_to_send = sorted_posts_ids.slice(cursor, cursor + post_range);
                    data['posts_ids'] = JSON.stringify(ids_to_send);
                    cursor += post_range;

                    console.log(cursor);
                    console.log(data);

                    // Send another request just after the previous one completed
                    this.open('post', k2_ajax_obj.ajax_url, true);
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
                    this.open('post', k2_ajax_obj.ajax_url, true);
                    this.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    let encoded_data = encodePostData(post_data);
                    this.send(encoded_data);
                }
            };

            xmlhttp.open('post', k2_ajax_obj.ajax_url, true);
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

    console.log(k2_ajax_obj);
});