<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ReviewApi\Test\Api;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Review\Model\ResourceModel\Rating\CollectionFactory as RatingCollectionFactory;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as RatingOptionCollectionFactory;
use Magento\Review\Model\Review;
use Magento\ReviewApi\Api\Data\ReviewInterface;
use Magento\TestFramework\Assert\AssertArrayContains;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Class UpdateReviewsTest
 */
class UpdateReviewsTest extends WebapiAbstract
{
    /**#@+
     * Service constants
     */
    const UPDATE_RESOURCE_PATH = '/V1/update-reviews';
    const UPDATE_SERVICE_NAME = 'reviewApiUpdateReviewsV1';
    const GET_RESOURCE_PATH = '/V1/get-reviews';
    const GET_SERVICE_NAME = 'reviewApiGetReviewsV1';

    /**
     * @var RatingCollectionFactory
     */
    private $ratingCollectionFactory;

    /**
     * @var RatingOptionCollectionFactory
     */
    private $ratingOptionCollectionFactory;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->ratingCollectionFactory = $objectManager->get(RatingCollectionFactory::class);
        $this->ratingOptionCollectionFactory = $objectManager->get(RatingOptionCollectionFactory::class);
    }

    /**#@-*/
    /**
     * @magentoApiDataFixture Magento/Review/_files/different_reviews.php
     * @magentoApiDataFixture Magento/Review/_files/active_ratings.php
     */
    public function testUpdateProductReviews()
    {
        /** @var \Magento\Review\Model\Rating $firstRating */
        $firstRating = $this->ratingCollectionFactory
            ->create()
            ->setPageSize(1)
            ->setCurPage(1)
            ->getFirstItem();

        /** @var \Magento\Review\Model\Rating\Option $firstRatingOption */
        $firstRatingOption = $this->ratingOptionCollectionFactory
            ->create()
            ->setPageSize(1)
            ->setCurPage(2)
            ->addRatingFilter($firstRating->getId())
            ->getFirstItem();

        /** @var \Magento\Review\Model\Rating $secondRating */
        $secondRating = $this->ratingCollectionFactory
            ->create()
            ->setPageSize(1)
            ->setCurPage(3)
            ->getFirstItem();

        /** @var \Magento\Review\Model\Rating\Option $secondRatingOption */
        $secondRatingOption = $this->ratingOptionCollectionFactory
            ->create()
            ->setPageSize(1)
            ->setCurPage(3)
            ->addRatingFilter($secondRating->getId())
            ->getFirstItem();

        $reviewsForUpdate = [
            [
                'review_id' => 1,
                'related_entity_id' => 1,
                'review_entity_id' => 1, //product
                'customer_nickname' => 'Updated Nickname',
                'title' => 'Updated Review Summary',
                'review_text' => 'Updated Review Text',
                'status' => Review::STATUS_APPROVED,
                'ratings' => [
                    [
                        'rating_name' => $firstRating->getRatingName(),
                        'rating_value' => $firstRatingOption->getValue(),
                    ],
                    [
                        'rating_name' => $secondRating->getRatingName(),
                        'rating_value' => $secondRatingOption->getValue(),
                    ],
                ],
                'store_id' => 1,
                'stores' => [1, 2],
            ],
            [
                'review_id' => 2,
                'related_entity_id' => 1,
                'review_entity_id' => 1, //product
                'customer_nickname' => 'Updated Customer Nickname',
                'title' => 'Updated Review Title',
                'review_text' => 'Updated Review Comment',
                'status' => Review::STATUS_PENDING,
                'ratings' => [
                    [
                        'rating_name' => $secondRating->getRatingName(),
                        'rating_value' => $secondRatingOption->getValue(),
                    ],
                ],
                'store_id' => 1,
                'stores' => [1],
            ],
        ];

        $expectedReviewsAfterUpdate = [
            [
                'review_id' => 1,
                'title' => 'Updated Review Summary',
                'review_text' => 'Updated Review Text',
                'customer_nickname' => 'Updated Nickname',
                'review_entity_id' => 1,
                'related_entity_id' => 1,
                'status' => Review::STATUS_APPROVED,
            ],
            [
                'review_id' => 2,
                'title' => 'Updated Review Title',
                'review_text' => 'Updated Review Comment',
                'customer_nickname' => 'Updated Customer Nickname',
                'review_entity_id' => 1,
                'related_entity_id' => 1,
                'status' => Review::STATUS_PENDING,
            ],
            [
                'review_id' => 3,
                'title' => '1 filter second review',
                'review_text' => 'Review text',
                'customer_nickname' => 'Nickname',
                'review_entity_id' => 1,
                'related_entity_id' => 1,
                'status' => Review::STATUS_APPROVED,
            ]
        ];

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::UPDATE_RESOURCE_PATH . '?' . http_build_query(['reviews' => $reviewsForUpdate]),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::UPDATE_SERVICE_NAME,
                'operation' => self::UPDATE_SERVICE_NAME . 'Execute',
            ],
        ];
        (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST)
            ? $this->_webApiCall($serviceInfo)
            : $this->_webApiCall($serviceInfo, ['reviews' => $reviewsForUpdate]);

        $actualData = $this->getReviews();

        self::assertEquals(3, $actualData['total_count']);
        AssertArrayContains::assert($expectedReviewsAfterUpdate, $actualData['items']);
    }

    /**
     * @return array
     */
    private function getReviews()
    {
        $requestData = [
            'searchCriteria' => [
                SearchCriteria::FILTER_GROUPS => [
                    [
                        'filters' => [
                            [
                                'field' => ProductInterface::SKU,
                                'value' => 'simple',
                                'condition_type' => 'eq',
                            ],
                        ],
                    ],
                ],
                SearchCriteria::SORT_ORDERS => [
                    [
                        'field' => ReviewInterface::CREATED_AT,
                        'direction' => SortOrder::SORT_DESC,
                    ],
                ],
                SearchCriteria::CURRENT_PAGE => 1,
                SearchCriteria::PAGE_SIZE => 10,
            ],
        ];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::GET_RESOURCE_PATH . '?' . http_build_query($requestData),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::GET_SERVICE_NAME,
                'operation' => self::GET_SERVICE_NAME . 'Execute',
            ],
        ];

        return (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST)
            ? $this->_webApiCall($serviceInfo)
            : $this->_webApiCall($serviceInfo, $requestData);
    }
}
