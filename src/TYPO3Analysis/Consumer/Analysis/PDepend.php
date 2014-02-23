<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

/**
 * Class PDepend
 *
 * A consumer to execute pDepend (https://github.com/pdepend/pdepend).
 * pDepend is a PHP port of javas design quality and metrics tool JDepend (http://clarkware.com/software/JDepend.html).
 *
 * We use this to generate the overview pyramide and generate and save various metrics per class.
 *
 * Message format (json encoded):
 *  [
 *      directory: Absolute path to folder which will be analyzed. E.g. /var/www/my/sourcecode
 *      versionId: Version ID to get the regarding version record from version database table
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\PDepend
 *
 * @package TYPO3Analysis\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class PDepend extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Executes the PDepend analysis on a given folder.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->setQueueOption('name', 'analysis.pdepend');
        $this->enableDeadLettering();

        $this->setRouting('analysis.pdepend');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->rejectMessage($message);
            return;
        }

        $dirToAnalyze = rtrim($messageData->directory, DIRECTORY_SEPARATOR);
        $pathParts = explode(DIRECTORY_SEPARATOR, $dirToAnalyze);
        $dirName = array_pop($pathParts);

        $basePath = implode(DIRECTORY_SEPARATOR, $pathParts) . DIRECTORY_SEPARATOR;
        $jDependChartFile = $basePath . 'jdepend-chart-' . $dirName . '.svg';
        $jDependXmlFile = $basePath . 'jdepend-xml-' . $dirName . '.xml';
        $overviewPyramidFile = $basePath . 'overview-pyramid-' . $dirName . '.svg';
        $summaryXmlFile = $basePath . 'summary-xml-' . $dirName . '.xml';

        // If there was already a pDepend run, all files must be exist. If yes, exit here
        if (file_exists($jDependChartFile) === true && file_exists($jDependXmlFile) === true
            && file_exists($overviewPyramidFile) === true && file_exists($summaryXmlFile) === true
        ) {
            $context = array(
                'versionId' => $messageData->versionId,
                'directory' => $messageData->directory
            );
            $this->getLogger()->info('Directory already analyzed with pDepend', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        // Execute pDepend
        $config = $this->getConfig();
        $filePattern = $config['Application']['PDepend']['FilePattern'];
        $command = $config['Application']['PDepend']['Binary'];
        $command .= ' --jdepend-chart=' . escapeshellarg($jDependChartFile);
        $command .= ' --jdepend-xml=' . escapeshellarg($jDependXmlFile);
        $command .= ' --overview-pyramid=' . escapeshellarg($overviewPyramidFile);
        $command .= ' --summary-xml=' . escapeshellarg($summaryXmlFile);
        $command .= ' --suffix=' . escapeshellarg($filePattern);
        $command .= ' --coderank-mode=inheritance,property,method ' . escapeshellarg(
            $dirToAnalyze . DIRECTORY_SEPARATOR
        );

        $context = array('directory' => $dirToAnalyze);
        $this->getLogger()->info('Start analyzing with pDepend', $context);

        try {
            $this->executeCommand($command);
        } catch (\Exception $e) {
            $this->rejectMessage($this->getMessage());
            return;
        }

        if (file_exists($jDependChartFile) !== true || file_exists($jDependXmlFile) !== true
            || file_exists($overviewPyramidFile) !== true || file_exists($summaryXmlFile) !== true
        ) {
            $context['jDependChart'] = $jDependChartFile;
            $context['jDependXml'] = $jDependXmlFile;
            $context['overviewPyramid'] = $overviewPyramidFile;
            $context['summaryXml'] = $summaryXmlFile;

            $this->getLogger()->critical('pDepend analysis result files does not exist!', $context);
            $this->rejectMessage($message);
            return;
        }

        // @todo add further consumer to parse and store the jDependXml- and summaryXml-file

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }
}
