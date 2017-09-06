<?php
/**
 * User: Xavier
 * Date: 9/5/17
 * Time: 3:39 PM 
*/

class XavierBaez_Page_Block_Html_Head extends Mage_Page_Block_Html_Head
{

    CONST MINIFY_LIBRARY_URL = 'store/min/?f=';
    CONST MINIFY_BUNDLE_DIR = 'store/min/b=';
    CONST MINIFY_FILE_SEPARATOR = '&f=';
    CONST DATE_FORMAT = 'Y-m-d.H-i-s';

    /**
     * Initialize template
     *
     */
    protected function _construct()
    {
        $this->setTemplate('page/html/head.phtml');
    }

    /**
     * @param array $srcFiles
     * @param bool $targetFile
     * @param bool $mustMerge
     * @param null $beforeMergeCallback
     * @param array $extensionsFilter
     * @return bool|string
     */
    protected function _mergeFiles(array $srcFiles, $targetFile = false, $mustMerge = false,
        $beforeMergeCallback = null, $extensionsFilter = array())
    {
        /**
        @var $databaseHelper Mage_Core_Helper_File_Storage_Database
        @var $coreHelper Mage_Core_Helper_Data
        */
        $databaseHelper = Mage::helper('core/file_storage_database');
        $coreHelper = Mage::helper('core');
        if ($databaseHelper->checkDbUsage()) {
            if (!file_exists($targetFile)) {
                $databaseHelper->saveFileToFilesystem($targetFile);
            }
            if (file_exists($targetFile)) {
                $filemtime = filemtime($targetFile);
            } else {
                $filemtime = null;
            }
            $result = $coreHelper->mergeFiles(
                $srcFiles, $targetFile, $mustMerge, $beforeMergeCallback, $extensionsFilter
            );
            if ($result && (filemtime($targetFile) > $filemtime)) {
                $databaseHelper->saveFile($targetFile);
            }

            return $result;


        } else {

            return $coreHelper->mergeFiles($srcFiles, $targetFile, $mustMerge, $beforeMergeCallback, $extensionsFilter);

        }
    }

    /**
     * Classify HTML head item and queue it into "lines" array
     *
     * @see self::getCssJsHtml()
     * @param array &$lines
     * @param string $itemIf
     * @param string $itemType
     * @param string $itemParams
     * @param string $itemName
     * @param array $itemThe
     */
    protected function _separateOtherHtmlHeadElements(&$lines, $itemIf, $itemType, $itemParams, $itemName, $itemThe)
    {
        $params = $itemParams ? ' ' . $itemParams : '';
        $href   = $itemName;
        switch ($itemType) {
            case 'rss':
                $lines[$itemIf]['other'][] = sprintf(
                    '<link href="%s"%s rel="alternate" type="application/rss+xml" />',
                    $href, $params
                );
                break;
            case 'link_rel':
                $lines[$itemIf]['other'][] = sprintf('<link %s href="%s" />', $params, $href);
                break;

            case 'skin_link_rel':
                if (!preg_match('/(jpe?g|png|jpg)/i', $itemName)) {
                    $lines[$itemIf]['other'][] = $this->_prepareStaticAndSkinElements(
                        '<link href="%s"%s />' . "\n", array(), array($params => array($href)), NULL
                    );
                }
                break;

            case 'external_js':
                $lines[$itemIf]['other'][] = sprintf(
                    '<script type="text/javascript" src="%s" %s></script>', $href, $params
                );
                break;

            case 'external_css':
                $lines[$itemIf]['other'][] = sprintf(
                    '<link rel="stylesheet" type="text/css" href="%s" %s/>',
                    $href, $params
                );
                break;
        }
    }

    /**
     * @param string $format
     * @param array $staticItems
     * @param array $skinItems
     * @param null $mergeCallback
     * @param null $minifiedCallback
     * @return string
     */
    protected function &_prepareStaticAndSkinElements($format, array $staticItems,
            array $skinItems, $mergeCallback = null, $minifiedCallback = null)
    {
        $isSecure = Mage::app()->getRequest()->isSecure();
        $rewriteBase = explode('/', Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), $isSecure);
        $rewriteBase = array_slice($rewriteBase, 3);
        $rewriteBase = '/' . implode('/', $rewriteBase);
        $designPackage = Mage::getDesign();
        // secure or insecure
        $baseJsUrl = Mage::getBaseUrl('js');
        $filesMTime = $items = $maxTimes = array();
        if ($mergeCallback && !is_callable($mergeCallback)) {
            $mergeCallback = null;
        }
        if ($minifiedCallback && !is_callable($minifiedCallback)) {
            $minifiedCallback = null;
        }
        // get static files from the js folder, no need for lookups
        foreach ($staticItems as $params => $rows) {
            if (count($staticItems) < 1) {
                break;
            }
            foreach ($rows as $name) {
                $items[$params][] = $mergeCallback ? Mage::getBaseDir() . DS . 'js' . DS . $name : $baseJsUrl . $name;
                $filesMTime[$params][] = $mergeCallback ? filemtime(Mage::getBaseDir() . DS . 'js' . DS . $name) : '';
            }
        }

        // lookup each file basing on current theme configuration
        foreach ($skinItems as $params => $rows) {
            if (count($skinItems) == 0) {
                break;
            }
            foreach ($rows as $name) {
                $items[$params][] = $mergeCallback ? $designPackage->getFilename($name, array('_type' => 'skin'))
                    : $designPackage->getSkinUrl($name, array());
                $filesMTime[$params][] = $mergeCallback ? filemtime(
                    $designPackage->getFilename($name, array('_type' => 'skin'))
                )
                    : '';
            }
        }

        $html = '';
        foreach ($items as $params => $rows) {
            // attempt to merge
            $mergedUrl = false;
            if ($minifiedCallback && !$mergeCallback) {
                $response = call_user_func($minifiedCallback, $rows, $filesMTime[$params]);
                if (is_array($response)) {
                    $minifiedUrl = $response[0];
                    $maxTimes = $response[1];
                    $sameFolderPath = $response[2];
                    isset($response[3]) ? $commonFolderPath = $response[3] : $commonFolderPath = false;
                } else {
                    $minifiedUrl = $response;
                }
            } elseif ($mergeCallback) {
                $response = call_user_func($mergeCallback, $rows, $filesMTime[$params], $minifiedCallback);
                if (is_array($response)) {
                    $mergedUrl = $mergedUrl[0];
                } else {
                    $mergedUrl = $response;
                }
            }
            if (!is_array($mergedUrl) && !empty($mergedUrl)) {
                $mergedUrl = array($mergedUrl);
            }
            // render elements
            $paramsSpaced = trim($params);
            $paramsSpaced = $paramsSpaced ? ' ' . $paramsSpaced : '';
            $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $isSecure);
            $uri  = @parse_url($baseUrl);
            $host = isset($uri['host']) ? $uri['host'] : '';
            $scheme = isset($uri['scheme']) ? $uri['scheme'] : '';
            $storeBaseUrl = $scheme . '://' . $host . $rewriteBase;
            if ($mergedUrl) {
                foreach ($mergedUrl as $key => $value) {
                    $html .= sprintf($format, $mergedUrl[$key], $paramsSpaced);
                }
            } elseif ($minifiedUrl) {
                    if ($commonFolderPath) {
                        $srcMinified = $storeBaseUrl . self::MINIFY_BUNDLE_DIR . $commonFolderPath .
                            self::MINIFY_FILE_SEPARATOR;
                    } else {
                        $srcMinified = $storeBaseUrl . self::MINIFY_LIBRARY_URL;
                    }
                    $numItems = count($minifiedUrl);
                    foreach ($minifiedUrl as $key => $src) {
                        $srcFiles = explode(',', $minifiedUrl[$key]);
                        $numItemsSrc = count($srcFiles);
                        foreach ($srcFiles as $keySrc => $file) {
                            if ($key + 1 == $numItems && $keySrc + 1 === $numItemsSrc) {
                                $srcMinified .= $sameFolderPath[$key] . DS . $file;
                            } else {
                                $srcMinified .= $sameFolderPath[$key] . DS . $file . ',';
                            }
                        }
                    }
                    $html .= sprintf($format, $srcMinified, $paramsSpaced);

            } else {
                foreach ($rows as $src) {
                    $html .= sprintf($format, $src, $paramsSpaced);
                }
            }
        }

        return $html;

    }


    /**
     * USAGE:
     * <action method="addExternalItem"><type>external_js</type><name>http://yui.yahooapis.com/2.8.2r1/build/imageloader/imageloader-min.js</name><params/></action>
     * <action method="addExternalItem"><type>external_css</type><name>http://yui.yahooapis.com/2.8.2r1/build/fonts/fonts-min.css</name><params/></action>
     *
     * Add HEAD External Item
     *
     * Allowed types:
     *  - js
     *  - js_css
     *  - skin_js
     *  - skin_css
     *  - rss
     *
     * @param string $type
     * @param string $name
     * @param string $params
     * @param string $if
     * @param string $cond
     * @return Mage_Page_Block_Html_Head
     */
    public function addExternalItem($type, $name, $params=null, $if=null, $cond=null)
    {
        parent::addItem($type, $name, $params, $if, $cond);
    }

    /**
     * @return string
     */
    public function getCssJsHtml()
    {

        $lines  = array();
        foreach ($this->_data['items'] as $item) {
            if (!is_null($item['cond']) && !$this->getData($item['cond']) || !isset($item['name'])) {
                continue;
            }
            $if     = !empty($item['if']) ? $item['if'] : '';
            $params = !empty($item['params']) ? $item['params'] : '';
            switch ($item['type']) {
                case 'js':        // js/*.js
                case 'skin_js':   // skin/*/*.js
                case 'js_css':    // js/*.css
                case 'skin_css':  // skin/*/*.css
                    $lines[$if][$item['type']][$params][$item['name']] = $item['name'];
                    break;
                default:
                    $this->_separateOtherHtmlHeadElements($lines, $if, $item['type'], $params, $item['name'], $item);
                    break;
            }
        }

        // prepare HTML
        $shouldMergeJs = Mage::getStoreConfigFlag('dev/js/merge_files');
        $shouldMergeCss = Mage::getStoreConfigFlag('dev/css/merge_css_files');
        $shouldMinifyJs = Mage::getStoreConfigFlag('dev/js/minify_files');
        $shouldMinifyCss = Mage::getStoreConfigFlag('dev/css/minify_css_files');

        $html   = '';
        foreach ($lines as $if => $items) {
            if (empty($items)) {
                continue;
            }
            if (!empty($if)) {
                $html .= '<!--[if '.$if.']>'."\n";
            }

            // static and skin css
            $html .= $this->_prepareStaticAndSkinElements(
                '<link rel="stylesheet" type="text/css" href="%s"%s />' . "\n",
                empty($items['js_css']) ? array() : $items['js_css'],
                empty($items['skin_css']) ? array() : $items['skin_css'],
                $shouldMergeCss ? array(Mage::getDesign(), 'getMergedCssUrl') : null,
                $shouldMinifyCss ? array($this, 'getMinifiedUrl') : null
            );

            // static and skin scripts
            $html .= $this->_prepareStaticAndSkinElements(
                '<script type="text/javascript" src="%s"%s></script>' . "\n",
                empty($items['js']) ? array() : $items['js'],
                empty($items['skin_js']) ? array() : $items['skin_js'],
                $shouldMergeJs ? array(Mage::getDesign(), 'getMergedJsUrl') : null,
                $shouldMinifyJs ? array($this, 'getMinifiedUrl') : null
            );

            // other stuff
            if (!empty($items['other'])) {
                $html .= $this->_prepareOtherHtmlHeadElements($items['other']) . "\n";
            }

            if (!empty($if)) {
                $html .= '<![endif]-->'."\n";
            }
        }

        return $html;

    }

    /**
     * @param $files
     * @param null $filesMTime
     * @return array
     */
    public function getMinifiedUrl($files, $filesMTime = null)
    {
        $pathInfo = $sameFolder = $sameFiles = $sameFolderPath = $targetFilenames = array();
        $isSecure = Mage::app()->getRequest()->isSecure();
        $storeUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $isSecure);
        $j = $sameKey = 0;
        $rewriteBase =  @parse_url($storeUrl);
        $rewriteBase = ltrim($rewriteBase['path'], '/');
        foreach ($files as $pathValue) {
            $pathInfo[] = pathinfo($pathValue);
        }
        foreach ($pathInfo as $i => $value) {
            if ($i > 0) {
                if ($pathInfo[$i]['dirname'] == $pathInfo[$i - 1]['dirname']) {
                    $sameFolder[$j] .= ',' . $i;
                    $sameFiles[$j] .= ',' . $pathInfo[$i]['basename'];
                    $sameFolderPath[$j] = str_replace($storeUrl, $rewriteBase, $pathInfo[$i]['dirname']);
                    $paths[$j] = explode('/', $sameFolderPath[$j]);
                } else {
                    $j++;
                    $sameFolder[$j] = $i;
                    $sameFiles[$j] = $pathInfo[$i]['basename'];
                    $sameFolderPath[$j] = str_replace($storeUrl, $rewriteBase, $pathInfo[$i]['dirname']);
                    $paths[$j] = explode('/', $sameFolderPath[$j]);
                }
            } else {
                $sameFolder[$j] = $i;
                $sameFiles[$j] = $pathInfo[$i]['basename'];
                $sameFolderPath[$j] = str_replace($storeUrl, $rewriteBase, $pathInfo[$i]['dirname']);
                $paths[$j] = explode('/', $sameFolderPath[$j]);
            }
        }
        foreach ($sameFolder as $key => $value) {
            $similarFilesMTime = array();
            $similarPaths = explode(',', $value);
            foreach ($similarPaths as $valuePath) {
                if ($filesMTime) {
                    $similarFilesMTime[] = $filesMTime[$valuePath];
                }
            }
            if ($filesMTime) {
                $maxTimes[] = max($similarFilesMTime);
            }
        }
        // find parent folder
        $sameKeys = array();
        for ($l =0; $l < count($paths); $l++) {
            for ($m = 0; $m < count($paths); $m++) {
                if ($l == $m) {
                    continue;
                }
                $diff = array_diff($paths[$l], $paths[$m]);
                $similarKey = key($diff);
                if ($similarKey > 0) {
                    $sameKeys[] = $similarKey - 1;
                }
            }
        }
        $sameKey = min($sameKeys);
        $commonFolderPath = (string) null;
        for ($k=0; $k<=$sameKey; $k++) {
            if ($sameKey == 0) {
                break;
            }
            if ($k == 0 ) {
                $commonFolderPath = $paths[0][$k];
            } else {
                $commonFolderPath .= DS . $paths[0][$k];
            }
        }
        foreach ($sameFolderPath as $key => $value) {
            if (count($commonFolderPath) == 0) {
                break;
            }
            $sameFolderPath[$key] = ltrim(str_replace($commonFolderPath, '', $value), '/');
        }

        $response = array($sameFiles, $maxTimes, $sameFolderPath, $commonFolderPath);

        return $response;

    }

    /**
     * Retrieve URL to robots file
     *
     * @return string
     */
    public function getRobots()
    {
        if (empty($this->_data['robots'])) {
            $this->_data['robots'] = Mage::getStoreConfig('design/head/default_robots');
        }

        return $this->_data['robots'];

    }

    /**
     * Remove External Item from HEAD entity
     *
     * <action method="removeExternalItem"><type>external_js</type><name>http://yui.yahooapis.com/2.8.2r1/build/imageloader/imageloader-min.js</name><params/></action>
     * <action method="removeExternalItem"><type>external_css</type><name>http://yui.yahooapis.com/2.8.2r1/build/fonts/fonts-min.css</name><params/></action>
     *
     *
     * @param string $type
     * @param string $name
     * @return Mage_Page_Block_Html_Head
     */
    public function removeExternalItem($type, $name)
    {
        parent::removeItem($type, $name);
    }

}