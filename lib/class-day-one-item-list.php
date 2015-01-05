<?php

class DayOneItemList {
    public $items = array();

    public function importFile( $filename ) {
        echo "Read DayOne export file \"" . $filename . "\":<br />\n";
        $lines = file( $filename );
        echo "" . count( $lines ) . " lines found<br />\n";

        $this->items = $this->parseItems( $lines );
        echo "" . count( $this->items ) . " entries found<br />\n";
    }

    public function count() {
        return count( $this->items );
    }

    // DayOne writes data like:
    // Date:        24. Juli 2013 09:48
    // Tags:        Freunde
    // Location:        Jollystraße 23, Karlsruhe, Baden-Württemberg, Deutschland
    // Weather:        24° Sunny
    // Photo:        2013-7-24.jpg
    // Starred
    //
    // Text
    private function parseItems( $lines ) {
        $items = array();
        $max   = count( $lines );
        for ( $i = 0; $i < $max; ++$i ) {
            $s   = $lines[$i];
            $pos = strpos( $s, "Date:" );
            if ( $pos !== false ) {
                // found data line
                $item           = new DayOneItem();
                $item->setDate( trim( substr( $s, 6 ) ) );
                $item->location = $this->getValue( $lines, $i, "Location:" );
                $item->weather  = $this->getValue( $lines, $i, "Weather:" );
                $item->tags     = $this->getValue( $lines, $i, "Tags:" );
                $item->photo    = $this->getValue( $lines, $i, "Photo:" );
                $item->starred  = $this->hasValue( $lines, $i, "Starred" );
                $item->text     = $this->getText( $lines, $i );

                $items[] = $item;
                
            }
        }

        return $items;
    }

    private function hasValue( $lines, $index, $key ) {
        $max = count( $lines );
        if ( $max > ( $index + 6 ) )
            $max = $index + 6;
        for ( $j = $index; $j < $max; ++$j ) {
            $s   = $lines[$j];
            $pos = strpos( $s, $key );
            if ( $pos !== false ) {
                return true;
            }
        }

        return false;
    }

    private function getValue( $lines, $index, $key ) {
        $max = count( $lines );
        if ( $max > ( $index + 6 ) )
            $max = $index + 6;
        for ( $j = $index; $j < $max; ++$j ) {
            $s   = $lines[$j];
            $pos = strpos( $s, $key );
            if ( $pos !== false ) {
                return trim( substr( $s, strlen( $key ) + 1 ) );
            }
        }

        return;
    }

    private function getText( $lines, $index ) {
        $max  = count( $lines );
        $text = "";

        // skip "Date:" line
        $index++;

        // first, scan until empty line is found to skip all meta fields
        while ( ( $index < $max ) && ( strlen( trim( $lines[$index] ) ) > 0 ) ) {
            $index++;
        }

        // skip empty line
        $index++;
        // now add all lines to text variable until "Date:" is found or max is reached
        while ( ( $index < $max ) && !( strpos( $lines[$index], "Date:" ) !== false ) ) {
            $text .= trim( $lines[$index] );
            $index++;
        }

        return trim( $text );
    }

    public function getItem( $date ) {
        $max = $this->count();

        // scan for exact date
        for ( $i = 0; $i < $max; ++$i ) {
            $item = $this->items[$i];

            if ( $item->matchDate( $date ) ) {
                $item->exists = true;
                return $item;
            }
        }

        //if not found, scan for rough date
        for ( $i = 0; $i < $max; ++$i ) {
            $item = $this->items[$i];

            if ( $item->matchRoughDate( $date ) ) {
                $item->exists = true;
                $item->isRoughDate = true;
                return $item;
            }
        }

        //if not found, scan for date only and text
        for ( $i = 0; $i < $max; ++$i ) {
            $item = $this->items[$i];
            
            if ( $item->sameDay( $date ) ) {
                $item->exists = true;
                return $item;
            }
        }
        
        return;
    }
}
?>