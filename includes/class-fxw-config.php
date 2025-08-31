<?php
/**
 * Configuration for the FoodXpress plugin.
 *
 * @since      1.0.1
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Config {

    /**
     * The default preparation time in minutes.
     *
     * @since 1.0.1
     */
    const DEFAULT_PREP_TIME = 20;

    /**
     * The default delivery radius in kilometers.
     *
     * @since 1.0.1
     */
    const DEFAULT_DELIVERY_RADIUS = 10;

    /**
     * The maximum number of retries for failed operations.
     *
     * @since 1.0.1
     */
    const MAX_RETRIES = 3;

    /**
     * The delay between retries in milliseconds.
     *
     * @since 1.0.1
     */
    const RETRY_DELAY = 200;
}
