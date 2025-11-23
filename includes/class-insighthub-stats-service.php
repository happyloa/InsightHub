<?php
/**
 * Stats service helper.
 *
 * @package InsightHub
 */

namespace InsightHub;

defined( 'ABSPATH' ) || exit;

/**
 * Class Stats_Service
 *
 * Provides helper methods to gather site statistics.
 */
class Stats_Service {
    /**
     * Get overall totals for key site metrics.
     *
     * @return array
     */
    public function get_totals() {
        $post_counts    = wp_count_posts( 'post' );
        $page_counts    = wp_count_posts( 'page' );
        $comment_counts = wp_count_comments();
        $user_counts    = count_users();

        return [
            'posts'    => isset( $post_counts->publish ) ? (int) $post_counts->publish : 0,
            'pages'    => isset( $page_counts->publish ) ? (int) $page_counts->publish : 0,
            'comments' => isset( $comment_counts->approved ) ? (int) $comment_counts->approved : 0,
            'users'    => isset( $user_counts['total_users'] ) ? (int) $user_counts['total_users'] : 0,
            'categories' => $this->get_taxonomy_count( 'category' ),
            'tags'       => $this->get_taxonomy_count( 'post_tag' ),
        ];
    }

    /**
     * Get recent activity metrics for posts and comments.
     *
     * @return array
     */
    public function get_recent_activity() {
        return [
            'posts_7_days'       => $this->count_posts_since_days( 7 ),
            'posts_30_days'      => $this->count_posts_since_days( 30 ),
            'comments_7_days'    => $this->count_comments_since_days( 7 ),
            'comments_30_days'   => $this->count_comments_since_days( 30 ),
        ];
    }

    /**
     * Get counts of published posts per post type, including custom types.
     *
     * @return array
     */
    public function get_post_type_totals() {
        $post_types = get_post_types(
            [
                'public'  => true,
                'show_ui' => true,
            ],
            'objects'
        );

        $totals = [];

        foreach ( $post_types as $post_type => $object ) {
            $counts = wp_count_posts( $post_type );
            $totals[ $post_type ] = [
                'label' => isset( $object->labels->singular_name ) ? $object->labels->singular_name : $post_type,
                'count' => isset( $counts->publish ) ? (int) $counts->publish : 0,
            ];
        }

        return $totals;
    }

    /**
     * Get basic WooCommerce order activity if WooCommerce is available and allowed.
     *
     * @param int $days Number of days to look back for order stats.
     *
     * @return array
     */
    public function get_woocommerce_activity( $days = 30 ) {
        if ( ! $this->can_access_woocommerce() ) {
            return [];
        }

        $after_date = gmdate( 'Y-m-d H:i:s', $this->get_timestamp_days_ago( $days ) );
        $statuses   = apply_filters( 'insighthub_woocommerce_statuses', [ 'processing', 'completed' ] );

        $orders = wc_get_orders(
            [
                'status'       => $statuses,
                'limit'        => -1,
                'return'       => 'ids',
                'date_created' => '>' . $after_date,
            ]
        );

        if ( ! is_array( $orders ) || empty( $orders ) ) {
            return [
                'orders'      => 0,
                'sales_total' => 0,
                'days'        => $days,
            ];
        }

        $total_sales = 0;

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );

            if ( $order ) {
                $total_sales += (float) $order->get_total();
            }
        }

        return [
            'orders'      => count( $orders ),
            'sales_total' => $total_sales,
            'days'        => $days,
        ];
    }

    /**
     * Count published posts in the last X days.
     *
     * @param int $days Days to look back.
     *
     * @return int
     */
    private function count_posts_since_days( $days ) {
        $after_date = gmdate( 'Y-m-d H:i:s', $this->get_timestamp_days_ago( $days ) );

        $posts = get_posts(
            [
                'post_type'        => 'post',
                'post_status'      => 'publish',
                'date_query'       => [
                    [
                        'after'     => $after_date,
                        'inclusive' => true,
                    ],
                ],
                'fields'           => 'ids',
                'nopaging'         => true,
                'suppress_filters' => true,
            ]
        );

        return is_array( $posts ) ? count( $posts ) : 0;
    }

    /**
     * Count comments created in the last X days.
     *
     * @param int $days Days to look back.
     *
     * @return int
     */
    private function count_comments_since_days( $days ) {
        $after_date = gmdate( 'Y-m-d H:i:s', $this->get_timestamp_days_ago( $days ) );

        $comments = get_comments(
            [
                'status'       => 'approve',
                'date_query'   => [
                    [
                        'after'     => $after_date,
                        'inclusive' => true,
                    ],
                ],
                'count'        => true,
                'hierarchical' => false,
            ]
        );

        return (int) $comments;
    }

    /**
     * Calculate timestamp for X days ago based on WordPress current time.
     *
     * @param int $days Days to subtract.
     *
     * @return int
     */
    private function get_timestamp_days_ago( $days ) {
        $seconds = absint( $days ) * DAY_IN_SECONDS;

        return current_time( 'timestamp', true ) - $seconds;
    }

    /**
     * Get count for taxonomy terms.
     *
     * @param string $taxonomy Taxonomy slug.
     *
     * @return int
     */
    private function get_taxonomy_count( $taxonomy ) {
        $count = wp_count_terms( $taxonomy, [ 'hide_empty' => false ] );

        if ( is_wp_error( $count ) ) {
            return 0;
        }

        return (int) $count;
    }

    /**
     * Determine if WooCommerce data can be queried.
     *
     * @return bool
     */
    private function can_access_woocommerce() {
        return class_exists( '\\WooCommerce' ) && function_exists( 'wc_get_orders' ) && current_user_can( 'manage_woocommerce' );
    }
}
