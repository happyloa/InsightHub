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
}
