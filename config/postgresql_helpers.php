<?php

/**
 * PostgreSQL Helper Functions
 * Replaces MySQL-specific date functions with PostgreSQL equivalents
 */

class PostgreSQLHelpers
{
    /**
     * Get current date (replaces CURDATE())
     */
    public static function getCurrentDate()
    {
        return "CURRENT_DATE";
    }

    /**
     * Get current timestamp (replaces NOW())
     */
    public static function getCurrentTimestamp()
    {
        return "CURRENT_TIMESTAMP";
    }

    /**
     * Extract date from timestamp (replaces DATE(column))
     */
    public static function extractDate($column)
    {
        return "DATE($column)";
    }

    /**
     * Date subtraction (replaces DATE_SUB)
     */
    public static function dateSubtract($interval)
    {
        switch ($interval) {
            case '1 DAY':
                return "CURRENT_DATE - INTERVAL '1 day'";
            case '1 WEEK':
                return "CURRENT_TIMESTAMP - INTERVAL '1 week'";
            case '1 MONTH':
                return "CURRENT_TIMESTAMP - INTERVAL '1 month'";
            case '1 YEAR':
                return "CURRENT_TIMESTAMP - INTERVAL '1 year'";
            default:
                return "CURRENT_TIMESTAMP - INTERVAL '$interval'";
        }
    }

    /**
     * Get date condition for today
     */
    public static function todayCondition($column)
    {
        return "DATE($column) = CURRENT_DATE";
    }

    /**
     * Get date condition for this week
     */
    public static function thisWeekCondition($column)
    {
        return "$column >= CURRENT_TIMESTAMP - INTERVAL '1 week'";
    }

    /**
     * Get date condition for this month
     */
    public static function thisMonthCondition($column)
    {
        return "$column >= CURRENT_TIMESTAMP - INTERVAL '1 month'";
    }

    /**
     * Get date condition for this year
     */
    public static function thisYearCondition($column)
    {
        return "$column >= CURRENT_TIMESTAMP - INTERVAL '1 year'";
    }

    /**
     * Format date for PostgreSQL DATE comparison
     */
    public static function formatDateForComparison($date)
    {
        return date('Y-m-d', strtotime($date));
    }

    /**
     * Get first day of current month
     */
    public static function getFirstDayOfMonth()
    {
        return date('Y-m-01');
    }

    /**
     * Convert MySQL ENUM to PostgreSQL CHECK constraint
     */
    public static function getEnumValues($enumName)
    {
        $enums = [
            'role' => ['student', 'admin'],
            'status' => ['pending', 'new', 'under_review', 'in_progress', 'rejected', 'implemented'],
            'deleted_by_role' => ['student', 'admin']
        ];

        return $enums[$enumName] ?? [];
    }
}
