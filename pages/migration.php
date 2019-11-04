<div class="wrap">

        <h2>Settings</h2>
        Modify this settings to migrate or not migrate databases

        <ul class="nav nav-tabs">
            <li class="active">
                <a href="#tab-1">DB Connection</a>
            </li>
            <li>
                <a href="#tab-2">Phocagallery</a>
            </li>
            <li>
                <a href="#tab-3">K2</a>
            </li>
        </ul>

        <div class="tab-content">
            <div id="tab-1" class="tab-pane active">
                <?php settings_errors(); ?>
                <h3>Database Connection Settings</h3>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('j2w_migration_settings');
                    do_settings_sections('j2w_settings');
                    submit_button();
                    ?>
                </form>

            </div>

            <div id="tab-2" class="tab-pane">
                <h2>Phocagallery Migration Settings</h2>

                <form id="phoca-migration-ajax-form">

                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th scope="row"><label for="phoca_empty_posts_categories">Empty posts and categories</label></th>
                            <td><input type="checkbox" name="phoca_empty_posts_categories" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phoca_migrate_categories">Migrate Categories</label></th>
                            <td><input type="checkbox" name="phoca_migrate_categories" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phoca_migrate_posts">Migrate Posts</label></th>
                            <td><input type="checkbox" name="phoca_migrate_posts" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phoca_delete_thumbs_resized">Delete thumbs and sizes</label></th>
                            <td><input type="checkbox" name="phoca_delete_thumbs_resized" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phoca_fix_categories_parents">Fix Categories Parents</label></th>
                            <td><input type="checkbox" name="phoca_fix_categories_parents" value="1"></td>
                        </tr>
                        </tbody>
                    </table>

                    <p class="submit"><input type="submit" name="phoca_submit" id="phoca-submit" class="button button-primary" value="Migrate Phocagallery"></p>
                </form>

                <div class="progressbar-wrap" id="phoca-progress" hidden>
                    <div id="phoca-progressbar"></div>
                </div>

            </div>

            <div id="tab-3" class="tab-pane">
                <h3>K2 Migration Settings</h3>

                <form id="k2-migration-ajax-form">

                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th scope="row"><label for="k2_empty_posts_categories">Empty posts and categories</label></th>
                            <td><input type="checkbox" name="k2_empty_posts_categories" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="k2_migrate_categories">Migrate Categories</label></th>
                            <td><input type="checkbox" name="k2_migrate_categories" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="k2_migrate_posts">Migrate Posts</label></th>
                            <td><input type="checkbox" name="k2_migrate_posts" value="1"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="k2_migrate_extra_fields">Migrate Extra Fields</label></th>
                            <td><input type="checkbox" name="k2_migrate_extra_fields" value="1"></td>
                        </tr>
                        </tbody>
                    </table>

                    <p class="submit"><input type="submit" name="k2_submit" id="k2-submit" class="button button-primary" value="Migrate K2"></p>
                </form>

                <div class="progressbar-wrap" id="k2-progress" hidden>
                    <div id="k2-progressbar"></div>
                </div>

            </div>
        </div>

</div>