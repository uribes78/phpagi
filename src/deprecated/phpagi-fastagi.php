#!/usr/local/bin/php -q
<?php
 /**
  * phpagi-fastagi.php : PHP FastAGI bootstrap
  * @see https://github.com/welltime/phpagi
  * @filesource http://phpagi.sourceforge.net/
  *
  * $Id: phpagi-fastagi.php,v 1.2 2005/05/25 18:43:48 pinhole Exp $
  *
  * Copyright (c) 2004, 2005 Matthew Asham <matthewa@bcwireless.net>, David Eder <david@eder.us>
  * All Rights Reserved.
  *
  * This software is released under the terms of the GNU Lesser General Public License v2.1
  * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
  *
  * We would be happy to list your phpagi based application on the phpagi
  * website.  Drop me an Email if you'd like us to list your program.
  *
  * @package phpAGI
  * @version 2.0
  * @example docs/fastagi.xinetd Example xinetd config file
  */

  /**
   * Modernized for PHP 8.1+
   * Please submit bug reports, patches, etc to https://github.com/welltime/phpagi
   *
   */

  require_once(__DIR__ . DIRECTORY_SEPARATOR . 'phpagi.php');

  $fastagi = new AGI();

  $fastagi->verbose(print_r($fastagi, true));

  $fastagi->config['fastagi']['basedir'] ??= __DIR__;

  $script = $fastagi->config['fastagi']['basedir'] . DIRECTORY_SEPARATOR . ($fastagi->request['agi_network_script'] ?? '');

  $mydir = dirname($fastagi->config['fastagi']['basedir']) . DIRECTORY_SEPARATOR;
  $dir = dirname($script) . DIRECTORY_SEPARATOR;
  if (!str_starts_with($dir, $mydir))
  {
    $fastagi->conlog("$script is not allowed to execute.");
    exit;
  }

  if (!file_exists($script))
  {
    $fastagi->conlog("$script does not exist.");
    exit;
  }

  if (isset($fastagi->config['fastagi']['setuid']) && $fastagi->config['fastagi']['setuid'])
  {
    $owner = fileowner($script);
    $group = filegroup($script);
    if (!posix_setgid($group) || !posix_setegid($group) || !posix_setuid($owner) || !posix_seteuid($owner))
    {
      $fastagi->conlog("failed to lower privileges.");
      exit;
    }
  }

  if (!is_readable($script))
  {
    $fastagi->conlog("$script is not readable.");
    exit;
  }

  require_once($script);
?>
