<?php
/**
 * The daily automatic-translation limit was reached.
 *
 * Its own type so callers can tell it apart without reading the message: every
 * other failure belongs to one item, but this one belongs to the day, and a
 * job that meets it should wait rather than fail everything it has left.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class Limit_Reached_Exception extends \RuntimeException {
}
