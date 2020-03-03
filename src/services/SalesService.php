<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\pluginsales\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Client;
use putyourlightson\pluginsales\models\SaleModel;
use putyourlightson\pluginsales\records\SaleRecord;
use yii\web\ForbiddenHttpException;

/**
 * @property int $count
 */
class SalesService extends Component
{
    /**
     * Returns plugin sales
     *
     * @return SaleModel[]
     */
    public function get(int $limit = null): array
    {
        $saleModels = [];
        $saleRecords = SaleRecord::find()->limit($limit)->all();

        foreach ($saleRecords as $saleRecord) {
            $saleModel = new SaleModel();
            $saleModel->setAttributes($saleRecord, false);
            $saleModels[] = $saleModel;
        }

        return $saleModels;
    }

    /**
     * Refreshes plugin sales
     *
     * @throws ForbiddenHttpException
     */
    public function refresh()
    {
        $client = new Client([
            'base_uri' => 'https://id.craftcms.com/',
            'cookies' => true,
        ]);

        $response = $client->get('login');
        $body = $response->getBody();

        // Extract CSRF token value
        preg_match('/csrfTokenValue:\s* "([\s\S]*?)"/', $body, $matches);
        $csrfTokenValue = $matches[1] ?? null;
        $csrfTokenValue = json_decode('"'.$csrfTokenValue.'"');

        if ($csrfTokenValue === null) {
            throw new ForbiddenHttpException(Craft::t('plugin-sales', 'Could not fetch a valid CSRF token.'));
        }

        $headers = [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfTokenValue,
        ];

        // Authenticate
        $client->post('index.php?p=actions//users/login', [
            'headers' => $headers,
            'multipart' => [
                [
                    'name' => 'loginName',
                    'contents' => 'putyourlightson',
                ],
                [
                    'name' => 'password',
                    'contents' => 'ETBRXGXZfZZTT3mxMKVYmRGG',
                ],
            ],
        ]);

        // Get total
        $response = $client->get('index.php?p=actions//craftnet/id/sales/get-sales&per_page=1', [
            'headers' => $headers,
        ]);

        $result = json_decode($response->getBody(), true);
        $total = $result['total'];
        $count = SaleRecord::find()->count();

        if ($total <= $count) {
            return;
        }

        $limit = $total - $count;

        // Get new sales
        $response = $client->get('index.php?p=actions//craftnet/id/sales/get-sales&per_page='.$limit, [
            'headers' => $headers,
        ]);

        $result = json_decode($response->getBody(), true);
        $sales = $result['data'];

        // Save sale records
        foreach ($sales as $sale) {
            $saleRecord = SaleRecord::find()
                ->where(['saleId' => $sale['id']])
                ->one();

            if ($saleRecord === null) {
                $saleRecord = new SaleRecord();
            }

            $saleRecord->setAttributes([
                'saleId' => $sale['id'],
                'pluginId' => $sale['plugin']['id'],
                'edition' => $sale['edition']['name'],
                'renewal' => ($sale['purchasableType'] == 'craftnet\\plugins\\PluginRenewal'),
                'grossAmount' => $sale['grossAmount'],
                'netAmount' => $sale['netAmount'],
                'email' => $sale['customer']['email'],
                'dateSold' => $sale['saleTime'],
            ], false);

            $saleRecord->save();
        }
    }
}
