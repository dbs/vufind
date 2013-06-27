<?php
/**
 * XSLT importer support methods for sitemaps.
 *
 * PHP version 5
 *
 * Copyright (c) Demian Katz 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records Wiki
 */
namespace VuFind\XSLT\Import;

/**
 * XSLT support class -- all methods of this class must be public and static;
 * they will be automatically made available to your XSL stylesheet for use
 * with the php:function() function.
 *
 * @category VuFind2
 * @package  Import_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records Wiki
 */
class VuFindSitemap extends VuFind
{
    /**
     * Load metadata about an HTML document using Aperture.
     *
     * @param string $htmlFile File on disk containing HTML.
     *
     * @return array
     */
    protected static function getApertureFields($htmlFile)
    {
        $xmlFile = tempnam('/tmp', 'apt');
        $cmd = static::getApertureCommand($htmlFile, $xmlFile, 'filecrawler');
        exec($cmd);

        // If we failed to process the file, give up now:
        if (!file_exists($xmlFile)) {
            throw new \Exception('Aperture failed.');
        }

        // Extract and decode the full text from the XML:
        $xml = file_get_contents($xmlFile);
        @unlink($xmlFile);
        preg_match('/<plainTextContent[^>]*>([^<]*)</ms', $xml, $matches);
        $final = isset($matches[1]) ?
            html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Extract the title from the XML:
        preg_match('/<title[^>]*>([^<]*)</ms', $xml, $matches);
        $title = isset($matches[1]) ?
            html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Extract the keywords from the XML:
        preg_match_all('/<keyword[^>]*>([^<]*)</ms', $xml, $matches);
        $keywords = array();
        if (isset($matches[1])) {
            foreach($matches[1] as $current) {
                $keywords[] = html_entity_decode($current, ENT_QUOTES, 'UTF-8');
            }
        }

        // Extract the description from the XML:
        preg_match('/<description[^>]*>([^<]*)</ms', $xml, $matches);
        $description = isset($matches[1])
            ? html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Send back the extracted fields:
        return array(
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'fulltext' => $final,
       );
    }

    /**
     * Load metadata about an HTML document using Tika.
     *
     * @param string $htmlFile File on disk containing HTML.
     *
     * @return array
     */
    protected static function getTikaFields($htmlFile)
    {
        // Extract and decode the full text from the XML:
        $xml = static::harvestWithTika($htmlFile, '--xml');

        // Extract the title from the XML:
        preg_match('/<title[^>]*>([^<]*)</ms', $xml, $matches);
        $title = isset($matches[1]) ?
            html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Extract the keywords from the XML:
        preg_match_all(
            '/<meta name="keywords" content="([^"]*)"/ms', $xml, $matches
        );
        $keywords = array();
        if (isset($matches[1])) {
            foreach($matches[1] as $current) {
                $keywords[] = html_entity_decode($current, ENT_QUOTES, 'UTF-8');
            }
        }

        // Extract the description from the XML:
        preg_match('/<meta name="description" content="([^"]*)"/ms', $xml, $matches);
        $description = isset($matches[1])
            ? html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : '';

        // Send back the extracted fields:
        return array(
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'fulltext' => $title . ' ' . static::harvestWithTika($htmlFile),
       );
    }

    /**
     * Extract key metadata from HTML.
     *
     * @param string $html HTML content.
     *
     * @return array
     */
    protected static function getHtmlFields($html)
    {
        // Extract the subjects from the HTML:
        preg_match_all('/<meta name="subject" content="([^"]*)"/ms', $html, $matches);
        $subjects = array();
        if (isset($matches[1])) {
            foreach($matches[1] as $current) {
                $subjects[] = html_entity_decode($current, ENT_QUOTES, 'UTF-8');
            }
        }

        // Extract the link types from the HTML:
        preg_match_all('/<meta name="category" content="([^"]*)"/ms', $html, $matches);
        $categories = array();
        if (isset($matches[1])) {
            foreach($matches[1] as $current) {
                $categories[] = html_entity_decode($current, ENT_QUOTES, 'UTF-8');
            }
        }

        // Extract the use count from the HTML:
        preg_match_all('/<meta name="useCount" content="([^"]*)"/ms', $html, $matches);
        $linkTypes = array();
        $useCount = isset($matches[1][0]) ? $matches[1][0] : 1;

        return array(
            'category' => $categories,
            'subject' => $subjects,
            'use_count' => $useCount,
        );
    }

    /**
     * Convert an associative array of fields into a Solr document.
     *
     * @param array $fields Field data
     *
     * @return string
     */
    public static function arrayToSolrXml($fields)
    {
        $xml = '';
        foreach ($fields as $key => $value) {
            $value = is_array($value) ? $value : array($value);
            foreach ($value as $current) {
                if (!empty($current)) {
                    $xml .= '<field name="' . $key . '">'
                        . htmlspecialchars($current) . '</field>';
                }
            }
        }
        return $xml;
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Aperture.
     * This method will only work if Aperture is properly configured in the
     * web/conf/fulltext.ini file.  Without proper configuration, this will
     * simply return an empty string.
     *
     * @param string $url URL of file to retrieve.
     *
     * @return string     text contents of file.
     * @access public
     */
    public static function getDocument($url)
    {
        $parser = static::getParser();
        if ($parser == 'None') {
            return '';
        }

        // Grab the HTML and write it to disk:
        $htmlFile = tempnam('/tmp', 'htm');
        $html = file_get_contents($url);
        file_put_contents($htmlFile, $html);

        // Use the appropriate full text parser:
        switch ($parser) {
        case 'Aperture':
            $fields = static::getApertureFields($htmlFile);
            break;
        case 'Tika':
            $fields = static::getTikaFields($htmlFile);
            break;
        default:
            throw new \Exception('Unexpected parser: ' . $parser);
        }

        // Clean up HTML file:
        @unlink($htmlFile);

        // Add data loaded directly from HTML:
        $fields += static::getHtmlFields($html);

        // Clean up/normalize full text:
        $fields['fulltext'] = trim(
            preg_replace(
                '/\s+/', ' ', static::stripBadChars($fields['fulltext'])
            )
        );

        // Use a hash of the URL for the ID:
        $fields['id'] = md5($url);

        // Add other key values:
        $fields['url'] = $url;
        $fields['last_indexed'] = date('Y-m-d\TH:i:s\Z');

        // Turn the array into XML:
        return static::arrayToSolrXml($fields);
    }
}