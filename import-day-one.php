<?php
/*
Plugin Name:  import-day-one
Plugin URI:   http://www.jlsoft.de/
Description:  Imports data from DayOne export file. Just add [import-day-one] to a page to import file.
Version:      0.1
License:      GPLv2 or later
Author:       Jan Loeffler
Author URI:   http://www.jlsoft.de/
Author Email: mail@jlsoft.de
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Online: http://www.gnu.org/licenses/gpl.txt
*/

require_once 'lib/class-day-one-item.php';
require_once 'lib/class-day-one-item-list.php';

// Add a callback to WordPress to execute our function import_day_one() when a page is rendered
add_shortcode( 'import-day-one', 'import_day_one' );

// Add [import-day-one] to a page to import file.
function import_day_one( $args ) {
    $dir      = wp_upload_dir();
    $filename = $dir['basedir'] . '/dayone.txt';

    $items = new DayOneItemList();
    $items->importFile( $filename );
    $sit   = new DayOneItem();

    $simulate          = false;
    $draft             = false;
    $i                 = 0;
    $item_count        = 0;
    $no_location_count = 0;
    $new_entries       = 0;
    $new_entries_added = 0;
    $updated           = 0;
    $args              = array(
         'post_type' => 'post',
         'nopaging' => 'true'
    );

    $post_query = new WP_Query( $args );
    while ( $post_query->have_posts() ) {
        $post_query->the_post();
        $post_id   = get_the_ID();
        $post_date = $sit->convertDate( trim( "" . get_the_date() . ' ' . get_the_time() ) );
        $location  = "";
        $weather   = "";
        $i++;

        $location     = get_post_meta( $post_id, 'location', true );
        $weather      = get_post_meta( $post_id, 'weather', true );
        $geo_latitude = get_post_meta( $post_id, 'geo_latitude', true );

        try {
        
            $item = $items->getItem( $post_date );
            if ( $item ) {
                if ( $item->isRoughDate ) {
                    echo '[' . $i . '] Correct date for post ' . $post_id . ' (' . $post_date . ' ' . get_the_title() . ') DayOne: ' . $item->toShortString() . "<br /><br />\n";
                    
                    if ( !$simulate ) {
                        $post = array(
                                      'ID'          => $post_id,
                                      'post_date'   => $item->getDate()
                                      );

                        wp_update_post( $post );
                    }
                }
                
                if ( !$location || !$weather ) {
                    // update post and add geo_location
                    $did_update = "";
                    if ( !$location && $item->location ) {
                        $location = (string) $item->location;
                        if ( !$simulate )
                            update_post_meta( $post_id, 'location', $location );
                        $did_update .= "location, ";
                    }

                    if ( !$weather && $item->weather ) {
                        $weather = (string) $item->weather;
                        if ( !$simulate )
                            update_post_meta( $post_id, 'weather', $weather );
                        $did_update .= "weather, ";
                    }

                    $did_update = trim( $did_update, ", " );
                    if ( strlen( $did_update ) > 0 ) {
                        echo '[' . $i . '] Update ' . $did_update . ' for post ' . $post_id . ' (' . $post_date . ' ' . get_the_title() . ') DayOne: ' . $item->toString() . "<br /><br />\n";
                        $updated++;
                    }
                }
            } else {
                echo '<!--[' . $i . '] No DayOne entry found for post ' . $post_id . ' (' . $post_date . ' ' . get_the_title() . ")-->\n";
            }

            if ( $location ) {
                $item_count++;

                if ( !$geo_latitude ) {
                    // search also for geo location
                    if ( !$item ) {
                        $item = new DayOneItem();
                        $item->setDate ( $post_date ) ;
                        $item->location = $location;
                    }

                    $item->findGeoLocation();

                    $geo_latitude  = $item->geo_latitude;
                    $geo_longitude = $item->geo_longitude;
                    if ( $geo_latitude ) {
                        // update post and add geo_location
                        echo '<!--[' . $i . '] Update geo location for post ' . $post_id . ' (' . get_the_title() . ') Location: ' . $location . ' (' . $geo_latitude . ',' . $geo_longitude . ")-->\n";
                        if ( !$simulate ) {
                            update_post_meta( $post_id, 'geo_latitude', (string) $geo_latitude );
                            update_post_meta( $post_id, 'geo_longitude', (string) $geo_longitude );
                        }
                    }
                }

            } else {
                echo '<!--[' . $i . '] No location for post ' . $post_id . ' (' . $post_date . ' ' . get_the_title() . ")-->\n";
                $no_location_count++;
            }
        }
        catch ( Exception $e ) {
            echo '[' . $i . '] Exception at post ' . $post_id . ' (' . $post_date . ' ' . get_the_title() . ') Location: ' . $location . ', Weather: ' . $weather . ': ', $e->getMessage(), "\n";
        }
    }

    // list new DayOne entries that are not in WordPress yet
    $max = $items->count();
    for ( $j = 0; $j < $max; ++$j ) {
        $item = $items->items[$j];

        if ( !$item->exists ) {
            echo '[' . $j . '] New DayOne entry: ' . $item->toString() . "<br /><br />\n";
            if ( !$simulate && add_post( $item, $draft ) ) {
                $new_entries_added++;
            }
            $new_entries++;
        }
    }

    echo $max . " day one entries total<br />\n";
    echo $i . " posts total<br />\n";
    echo $item_count . " posts with location<br />\n";
    echo $no_location_count . " posts without location<br />\n";
    echo $updated . " posts updated<br />\n";
    echo $new_entries . " new DayOne entries found<br />\n";
    echo $new_entries_added . " new DayOne entries added to WordPress<br />\n";

    wp_reset_postdata();
}

function add_post( $item, $draft ) {

    try {
        // WordPress date must be in form: '2010-02-23 18:57:33'
        // DayOne comes with: '24. Juli 2013 09:48' or '15 Sep 2013 15:49'
        // date (, strtotime('24 July 2013 09:48:00') )
        
        // post_stats: [Published, Draft, Private]
        if ( $draft ) {
            $post = array(
                'post_content' => $item->text,
                'post_title'   => $item->getDate(),
                'post_status'  => 'draft',
                'post_type'    => 'post',
                'post_date'    => $item->getDate(),
                'tags_input'   => $item->getTags()
            );
        } else {
            $post = array(
                          'post_content' => $item->text,
                          'post_title'   => $item->getDate(),
                          'post_status'  => 'publish',
                          'post_type'    => 'post',
                          'post_date'    => $item->getDate(),
                          'tags_input'   => $item->getTags()
                          );
        }

        echo "Add post to WP: " . $item->toString() . "<br /><br />\n";

        // insert the post to WordPress
        $post_id = wp_insert_post( $post );

        set_post_format( $post_id, 'image' );

        $weather = $item->weather;
        if ( $weather ) {
            update_post_meta( $post_id, 'weather', (string) $weather );
        }

        $location = $item->location;
        if ( $location ) {
            update_post_meta( $post_id, 'location', (string) $location );

            // search also for geo location
            $item->findGeoLocation();

            $geo_latitude  = $item->geo_latitude;
            $geo_longitude = $item->geo_longitude;
            if ( $geo_latitude ) {
                // update post and add geo_location
                echo '<!--Update geo location for post ' . $post_id . ' (' . $item->getDate() . ') Location: ' . $location . ' (' . $geo_latitude . ',' . $geo_longitude . ")-->\n";
                update_post_meta( $post_id, 'geo_latitude', (string) $geo_latitude );
                update_post_meta( $post_id, 'geo_longitude', (string) $geo_longitude );
            }
        }

    }
    catch ( Exception $e ) {
        echo 'Exception at creating post ' . $item->toString(), $e->getMessage(), "\n";
    }

    return $post_id;
}
?>