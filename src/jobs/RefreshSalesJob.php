<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\pluginsales\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use putyourlightson\pluginsales\PluginSales;
use yii\queue\Queue;

class RefreshSalesJob extends BaseJob
{
    /**
     * @var Queue|QueueInterface
     */
    private Queue|QueueInterface $_queue;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $this->_queue = $queue;

        PluginSales::$plugin->sales->refresh([$this, 'setProgressHandler']);
    }

    /**
     * Handles setting the progress.
     */
    public function setProgressHandler(int $count, int $total)
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->_queue, $progress);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('plugin-sales', 'Refreshing plugin sales');
    }
}
