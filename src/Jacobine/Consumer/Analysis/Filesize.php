<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Analysis;

use Jacobine\Consumer\ConsumerAbstract;

/**
 * Class Filesize
 *
 * A consumer to measure the filefize of given filename.
 * The filesize will be written back into the regarding version record of version database table.
 * This is the reason why a versionId is necessary in message.
 *
 * Message format (json encoded):
 *  [
 *      versionId: Version ID to get the regarding version record from version database table
 *      filename: Filename to measure the filesize of
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\Filesize
 *
 * @package Jacobine\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Filesize extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Determines the filesize in bytes and stores them in version database table.';
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

        $this->setQueueOption('name', 'analysis.filesize');
        $this->enableDeadLettering();

        $this->setRouting('analysis.filesize');
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
        $record = $this->getVersionFromDatabase($messageData->versionId);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $context = array('versionId' => $messageData->versionId);
            $this->getLogger()->critical('Record does not exist in version table', $context);
            $this->rejectMessage($message);
            return;
        }

        // If the filesize is already saved exit here
        if (isset($record['size_tar']) === true && $record['size_tar']) {
            $context = array('versionId' => $messageData->versionId);
            $this->getLogger()->info('Record marked as already analyzed', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no file, exit here
        if (file_exists($messageData->filename) !== true) {
            $context = array('filename' => $messageData->filename);
            $this->getLogger()->critical('File does not exist', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        $this->getLogger()->info('Getting filesize', array('filename' => $messageData->filename));
        $fileSize = filesize($messageData->filename);

        // Update the 'downloaded' flag in database
        $this->saveFileSizeOfVersionInDatabase($record['id'], $fileSize);

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Receives a single version of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id)
    {
        $fields = array('id', 'size_tar');
        $rows = $this->getDatabase()->getRecords($fields, 'versions', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Updates a single version and sets the 'size_tar' value
     *
     * @param integer $id
     * @param integer $fileSize
     * @return void
     */
    private function saveFileSizeOfVersionInDatabase($id, $fileSize)
    {
        $this->getDatabase()->updateRecord('versions', array('size_tar' => $fileSize), array('id' => $id));

        $context = array('filesize' => $fileSize, 'versionId' => $id);
        $this->getLogger()->info('Save filesize for version record', $context);
    }
}
