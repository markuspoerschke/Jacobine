<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Fixtures;

/**
 * Class MessageQueueOptions
 *
 * Fixture class to provide default options of \TYPO3Analysis\Helper\MessageQueue
 *
 * @package TYPO3Analysis\Tests\Fixtures
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */

class MessageQueueOptions {

    /**
     * Default queue options
     *
     * @var array
     */
    public $defaultQueueOptions = [
        'name' => '',
        'passive' => false,
        'durable' => false,
        'exclusive' => false,
        'auto_delete' => true,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    ];

    /**
     * Default exchange options
     *
     * @var array
     */
    public $defaultExchangeOptions = [
        'name' => '',
        'type' => 'topic',
        'passive' => false,
        'durable' => false,
        'auto_delete' => true,
        'internal' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    ];
}