<?php

class CommentFunctions {
    /**
     * The following four functions are borrowed
     * from includes/wikia/GlobalFunctionsNY.php
     */
    static function dateDiff( $date1, $date2 ) {
        $dtDiff = $date1 - $date2;

        $totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
        $totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
        $dif['mo'] = intval( $totalDays / 30 );
        $dif['d'] = $totalDays;
        $dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
        $dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
        $dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

        return $dif;
    }

    static function getTimeOffset( $time, $timeabrv, $timename ) {
        $timeStr = ''; // misza: initialize variables, DUMB FUCKS!
        if( $time[$timeabrv] > 0 ) {
            // Give grep a chance to find the usages:
            // comments-time-days, comments-time-hours, comments-time-minutes, comments-time-seconds, comments-time-months
            $timeStr = wfMessage( "comments-time-{$timename}", $time[$timeabrv] )->parse();
        }
        if( $timeStr ) {
            $timeStr .= ' ';
        }
        return $timeStr;
    }

    static function getTimeAgo( $time ) {
        $timeArray = self::dateDiff( time(), $time );
        $timeStr = '';
        $timeStrMo = self::getTimeOffset( $timeArray, 'mo', 'months' );
        $timeStrD = self::getTimeOffset( $timeArray, 'd', 'days' );
        $timeStrH = self::getTimeOffset( $timeArray, 'h', 'hours' );
        $timeStrM = self::getTimeOffset( $timeArray, 'm', 'minutes' );
        $timeStrS = self::getTimeOffset( $timeArray, 's', 'seconds' );

        if ( $timeStrMo ) {
            $timeStr = $timeStrMo;
        } else {
            $timeStr = $timeStrD;
            if( $timeStr < 2 ) {
                $timeStr .= $timeStrH;
                $timeStr .= $timeStrM;
                if( !$timeStr ) {
                    $timeStr .= $timeStrS;
                }
            }
        }
        if( !$timeStr ) {
            $timeStr = wfMessage( 'comments-time-seconds', 1 )->parse();
        }
        return $timeStr;
    }

    /**
     * Makes sure that link text is not too long by changing too long links to
     * <a href=#>http://www.abc....xyz.html</a>
     *
     * @param $matches Array
     * @return String: shortened URL
     */
    public static function cutCommentLinkText( $matches ) {
        $tagOpen = $matches[1];
        $linkText = $matches[2];
        $tagClose = $matches[3];

        $image = preg_match( "/<img src=/i", $linkText );
        $isURL = ( preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $linkText ) ? true : false );

        if( $isURL && !$image && strlen( $linkText ) > 30 ) {
            $start = substr( $linkText, 0, ( 30 / 2 ) - 3 );
            $end = substr( $linkText, strlen( $linkText ) - ( 30 / 2 ) + 3, ( 30 / 2 ) - 3 );
            $linkText = trim( $start ) . wfMsg( 'ellipsis' ) . trim( $end );
        }
        return $tagOpen . $linkText . $tagClose;
    }

    /**
     * Simple spam check -- checks the supplied text against MediaWiki's
     * built-in regex-based spam filters
     *
     * @param $text String: text to check for spam patterns
     * @return Boolean: true if it contains spam, otherwise false
     */
    public static function isSpam( $text ) {
        global $wgSpamRegex, $wgSummarySpamRegex;

        $retVal = false;
        // Allow to hook other anti-spam extensions so that sites that use,
        // for example, AbuseFilter, Phalanx or SpamBlacklist can add additional
        // checks
        wfRunHooks( 'Comments::isSpam', array( &$text, &$retVal ) );
        if ( $retVal ) {
            // Should only be true here...
            return $retVal;
        }

        // Run text through $wgSpamRegex (and $wgSummarySpamRegex if it has been specified)
        if ( $wgSpamRegex && preg_match( $wgSpamRegex, $text ) ) {
            return true;
        }

        if ( $wgSummarySpamRegex && is_array( $wgSummarySpamRegex ) ) {
            foreach ( $wgSummarySpamRegex as $spamRegex ) {
                if ( preg_match( $spamRegex, $text ) ) {
                    return true;
                }
            }
        }

        return $retVal;
    }

    /**
     * Checks the supplied text for links
     *
     * @param $text String: text to check
     * @return Boolean: true if it contains links, otherwise false
     */
    public static function haveLinks( $text ) {
        $linkPatterns = array(
            '/(https?)|(ftp):\/\//',
            '/=\\s*[\'"]?\\s*mailto:/',
        );
        foreach ( $linkPatterns as $linkPattern ) {
            if ( preg_match( $linkPattern, $text ) ) {
                return true;
            }
        }

        return false;
    }
}