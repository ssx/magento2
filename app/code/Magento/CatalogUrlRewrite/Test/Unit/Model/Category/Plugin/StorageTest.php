<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogUrlRewrite\Test\Unit\Model\Category\Plugin;

use Magento\CatalogUrlRewrite\Model\Category\Plugin\Storage as CategoryStoragePlugin;
use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product as ProductResourceModel;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\UrlRewrite\Model\MergeDataProvider;
use Magento\UrlRewrite\Model\MergeDataProviderFactory;
use Magento\UrlRewrite\Model\StorageInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /**
     * @var CategoryStoragePlugin
     */
    private $plugin;

    /**
     * @var UrlFinderInterface|MockObject
     */
    private $urlFinder;

    /**
     * @var StorageInterface|MockObject
     */
    private $storage;

    /**
     * @var ProductResourceModel|MockObject
     */
    private $productResourceModel;

    protected function setUp(): void
    {
        $this->storage = $this->getMockBuilder(StorageInterface::class)
            ->getMockForAbstractClass();
        $this->urlFinder = $this->getMockBuilder(UrlFinderInterface::class)
            ->getMockForAbstractClass();
        $this->productResourceModel = $this->getMockBuilder(ProductResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mergeDataProviderFactory = $this->createMock(MergeDataProviderFactory::class);
        $mergeDataProviderFactory->method('create')
            ->willReturnCallback(
                function () {
                    return new MergeDataProvider();
                }
            );

        $this->plugin = new CategoryStoragePlugin(
            $this->urlFinder,
            $this->productResourceModel,
            $mergeDataProviderFactory
        );
    }

    /**
     * test AfterReplace method
     *
     * @dataProvider afterReplaceDataProvider
     */
    public function testAfterReplace(
        array $oldUrlsData,
        array $newUrlsData,
        array $expected
    ) {
        $serializer = new Json();
        $categoryId = 4;
        $defaultUrlData = [
            UrlRewrite::URL_REWRITE_ID => null,
            UrlRewrite::ENTITY_ID => null,
            UrlRewrite::ENTITY_TYPE => 'product',
            UrlRewrite::IS_AUTOGENERATED => 1,
            UrlRewrite::REQUEST_PATH => null,
            UrlRewrite::TARGET_PATH => null,
            UrlRewrite::STORE_ID => 1,
            UrlRewrite::REDIRECT_TYPE => 0,
            UrlRewrite::DESCRIPTION => null,
            UrlRewrite::METADATA => $serializer->serialize(['category_id' => $categoryId]),
        ];
        $newUrlRewrites = [];
        $oldUrlRewrites = [];
        foreach ($oldUrlsData as $urlData) {
            $oldUrlRewrites[] = new UrlRewrite($urlData + $defaultUrlData, $serializer);
        }
        foreach ($newUrlsData as $urlData) {
            $newUrlRewrites[] = new UrlRewrite($urlData + $defaultUrlData, $serializer);
        }

        if ($expected['findAllByData']) {
            $this->urlFinder->expects($this->once())
                ->method('findAllByData')
                ->willReturn($oldUrlRewrites);
        } else {
            $this->urlFinder->expects($this->never())
                ->method('findAllByData');
        }

        if ($expected['saveMultiple']) {
            $this->productResourceModel->expects($this->once())
                ->method('saveMultiple')
                ->with($expected['saveMultiple'])
                ->willReturnSelf();
        } else {
            $this->productResourceModel->expects($this->never())
                ->method('saveMultiple');
        }

        $this->plugin->afterReplace($this->storage, $newUrlRewrites, $newUrlRewrites);
    }

    public static function afterReplaceDataProvider(): array
    {
        return [
            [
                [
                    [
                        UrlRewrite::URL_REWRITE_ID => 1,
                        UrlRewrite::ENTITY_ID => 1,
                        UrlRewrite::ENTITY_TYPE => 'category',
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11.html',
                        UrlRewrite::STORE_ID => 1,
                    ]
                ],
                [
                    [
                        UrlRewrite::ENTITY_TYPE => 'category',
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11.html',
                        UrlRewrite::STORE_ID => 1,
                    ]
                ],
                [
                    'findAllByData' => false,
                    'saveMultiple' => false,
                ]
            ],
            [
                [
                    [
                        UrlRewrite::URL_REWRITE_ID => 1,
                        UrlRewrite::ENTITY_ID => 1,
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11/simple1.html',
                        UrlRewrite::STORE_ID => 1,
                    ],
                    [
                        UrlRewrite::URL_REWRITE_ID => 2,
                        UrlRewrite::ENTITY_ID => 1,
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11/simple1.html',
                        UrlRewrite::STORE_ID => 2,
                    ],
                    [
                        UrlRewrite::URL_REWRITE_ID => 3,
                        UrlRewrite::ENTITY_ID => 2,
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11/simple2.html',
                        UrlRewrite::STORE_ID => 2,
                    ],
                ],
                [
                    [
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11/simple1.html',
                        UrlRewrite::STORE_ID => 1,
                    ],
                    [
                        UrlRewrite::REQUEST_PATH => 'cat1/cat11/simple2.html',
                        UrlRewrite::STORE_ID => 2,
                    ],
                ],
                [
                    'findAllByData' => true,
                    'saveMultiple' => [
                        [
                            'url_rewrite_id' => 1,
                            'category_id' => 4,
                            'product_id' => 1,
                        ],
                        [
                            'url_rewrite_id' => 3,
                            'category_id' => 4,
                            'product_id' => 2,
                        ]
                    ],
                ]
            ]
        ];
    }

    /**
     * test BeforeDeleteByData method
     */
    public function testBeforeDeleteByData()
    {
        $data = [1, 2, 3];
        $this->productResourceModel->expects(static::once())
            ->method('removeMultipleByProductCategory')
            ->with($data)->willReturnSelf();
        $this->plugin->beforeDeleteByData($this->storage, $data);
    }
}
