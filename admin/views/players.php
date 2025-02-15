<div class="wrap">
    <h1>FightTicket Player Players</h1>
    
    <div class="notice notice-success is-dismissible" style="display:none;" id="ft-player-notice"></div>

    <div class="card">
        <h2>Add New Player</h2>
        <form id="ft-player-add-player">
            <table class="form-table">
                <tr>
                    <th><label for="name">Player Name</label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="live_input_id">Live Input ID</label></th>
                    <td><input type="text" id="live_input_id" name="live_input_id" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="event_date">Event Date/Time</label></th>
                    <td><input type="datetime-local" id="event_date" name="event_date" required></td>
                </tr>
                <tr>
                    <th><label for="background_image">Background Image URL</label></th>
                    <td>
                        <input type="url" id="background_image" name="background_image" class="regular-text">
                        <p class="description">Optional: URL for the countdown background image</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Add Player</button>
            </p>
        </form>
    </div>

    <div class="card">
        <h2>Existing Players</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Event Date</th>
                    <th>Shortcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $player): ?>
                <tr>
                    <td><?php echo esc_html($player->name); ?></td>
                    <td><?php echo esc_html($player->event_date); ?></td>
                    <td><code>[ft_player id="<?php echo esc_attr($player->id); ?>"]</code></td>
                    <td>
                        <button class="button button-small delete-player" data-id="<?php echo esc_attr($player->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div> 