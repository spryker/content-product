<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Product\Business\Product;

use Exception;
use Generated\Shared\Transfer\PriceProductTransfer;
use Generated\Shared\Transfer\ProductAbstractTransfer;
use Spryker\Zed\Product\Business\Attribute\AttributeManagerInterface;
use Spryker\Zed\Product\Business\Attribute\AttributeProcessor;
use Spryker\Zed\Product\Business\Exception\MissingProductException;
use Spryker\Zed\Product\Business\Transfer\ProductTransferMapper;
use Spryker\Zed\Product\Dependency\Facade\ProductToLocaleInterface;
use Spryker\Zed\Product\Dependency\Facade\ProductToPriceInterface;
use Spryker\Zed\Product\Dependency\Facade\ProductToTouchInterface;
use Spryker\Zed\Product\Dependency\Facade\ProductToUrlInterface;
use Spryker\Zed\Product\Persistence\ProductQueryContainerInterface;

class ProductAbstractManager implements ProductAbstractManagerInterface
{

    /**
     * @var \Spryker\Zed\Product\Business\Attribute\AttributeManagerInterface
     */
    protected $attributeManager;

    /**
     * @var \Spryker\Zed\Product\Persistence\ProductQueryContainerInterface
     */
    protected $productQueryContainer;

    /**
     * @var \Spryker\Zed\Product\Dependency\Facade\ProductToTouchInterface
     */
    protected $touchFacade;

    /**
     * @var \Spryker\Zed\Product\Dependency\Facade\ProductToUrlInterface
     */
    protected $urlFacade;

    /**
     * @var \Spryker\Zed\Product\Dependency\Facade\ProductToLocaleInterface
     */
    protected $localeFacade;

    /**
     * @var \Spryker\Zed\Product\Dependency\Facade\ProductToPriceInterface
     */
    protected $priceFacade;

    /**
     * @var \Spryker\Zed\Product\Business\Product\ProductAbstractAssertionInterface
     */
    protected $productAbstractAssertion;

    /**
     * @var \Spryker\Zed\Product\Business\Product\ProductConcreteManagerInterface
     */
    protected $productConcreteManager;

    /**
     * @var \Spryker\Zed\Product\Dependency\Plugin\ProductAbstractPluginInterface[]
     */
    protected $pluginsCreateCollection;

    /**
     * @var \Spryker\Zed\Product\Dependency\Plugin\ProductAbstractPluginInterface[]
     */
    protected $pluginsUpdateCollection;

    /**
     * @var \Spryker\Zed\Product\Dependency\Plugin\ProductAbstractPluginInterface[]
     */
    protected $pluginsReadCollection;

    public function __construct(
        AttributeManagerInterface $attributeManager,
        ProductQueryContainerInterface $productQueryContainer,
        ProductToTouchInterface $touchFacade,
        ProductToUrlInterface $urlFacade,
        ProductToLocaleInterface $localeFacade,
        ProductToPriceInterface $priceFacade,
        ProductConcreteManagerInterface $productConcreteManager,
        ProductAbstractAssertionInterface $productAbstractAssertion,
        array $pluginsCreateCollection,
        array $pluginsReadCollection,
        array $pluginsUpdateCollection
    ) {
        $this->attributeManager = $attributeManager;
        $this->productQueryContainer = $productQueryContainer;
        $this->touchFacade = $touchFacade;
        $this->urlFacade = $urlFacade;
        $this->localeFacade = $localeFacade;
        $this->priceFacade = $priceFacade;
        $this->productConcreteManager = $productConcreteManager;
        $this->productAbstractAssertion = $productAbstractAssertion;
        $this->pluginsCreateCollection = $pluginsCreateCollection;
        $this->pluginsReadCollection = $pluginsReadCollection;
        $this->pluginsUpdateCollection = $pluginsUpdateCollection;
    }

    /**
     * @param string $sku
     *
     * @return bool
     */
    public function hasProductAbstract($sku)
    {
        return $this->productQueryContainer
            ->queryProductAbstractBySku($sku)
            ->count() > 0;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @throws \Exception
     *
     * @return int
     */
    public function createProductAbstract(ProductAbstractTransfer $productAbstractTransfer)
    {
        $this->productQueryContainer->getConnection()->beginTransaction();

        try {
            $this->productAbstractAssertion->assertSkuIsUnique($productAbstractTransfer->getSku());

            $this->triggerBeforeCreatePlugins($productAbstractTransfer);

            $productAbstractEntity = $this->persistEntity($productAbstractTransfer);

            $idProductAbstract = $productAbstractEntity->getPrimaryKey();
            $productAbstractTransfer->setIdProductAbstract($idProductAbstract);

            $this->attributeManager->persistProductAbstractLocalizedAttributes($productAbstractTransfer);
            $this->persistPrice($productAbstractTransfer);

            $this->triggerAfterCreatePlugins($productAbstractTransfer);

            $this->productQueryContainer->getConnection()->commit();
            return $idProductAbstract;

        } catch (Exception $e) {
            $this->productQueryContainer->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @throws \Exception
     *
     * @return int
     */
    public function saveProductAbstract(ProductAbstractTransfer $productAbstractTransfer)
    {
        $this->productQueryContainer->getConnection()->beginTransaction();

        try {
            $idProductAbstract = (int)$productAbstractTransfer
                ->requireIdProductAbstract()
                ->getIdProductAbstract();

            $this->productAbstractAssertion->assertProductExists($idProductAbstract);
            $this->productAbstractAssertion->assertSkuIsUniqueWhenUpdatingProduct($idProductAbstract, $productAbstractTransfer->getSku());

            $this->triggerBeforeUpdatePlugins($productAbstractTransfer);

            $this->persistEntity($productAbstractTransfer);

            $this->attributeManager->persistProductAbstractLocalizedAttributes($productAbstractTransfer);
            $this->persistPrice($productAbstractTransfer);

            $this->triggerAfterUpdatePlugins($productAbstractTransfer);

            $this->productQueryContainer->getConnection()->commit();
            return $idProductAbstract;

        } catch (Exception $e) {
            $this->productQueryContainer->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * @param string $sku
     *
     * @return int|null
     */
    public function getProductAbstractIdBySku($sku)
    {
        $productAbstract = $this->productQueryContainer
            ->queryProductAbstractBySku($sku)
            ->findOne();

        if (!$productAbstract) {
            return null;
        }

        return $productAbstract->getIdProductAbstract();
    }

    /**
     * @param int $idProductAbstract
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer|null
     */
    public function getProductAbstractById($idProductAbstract)
    {
        $productAbstractEntity = $this->productQueryContainer
            ->queryProductAbstract()
            ->filterByIdProductAbstract($idProductAbstract)
            ->findOne();

        if (!$productAbstractEntity) {
            return null;
        }

        $transferGenerator = new ProductTransferMapper();
        $productAbstractTransfer = $transferGenerator->convertProductAbstract($productAbstractEntity);
        $productAbstractTransfer = $this->loadLocalizedAttributes($productAbstractTransfer);
        $productAbstractTransfer = $this->loadTaxSetId($productAbstractTransfer);
        $productAbstractTransfer = $this->loadPrice($productAbstractTransfer);

        $this->triggerLoadPlugins($productAbstractTransfer);

        return $productAbstractTransfer;
    }

    /**
     * @param int $idProductAbstract
     *
     * @return \Spryker\Zed\Product\Business\Attribute\AttributeProcessorInterface
     */
    public function getProductAttributeProcessor($idProductAbstract)
    {
        $attributeProcessor = new AttributeProcessor();
        $productAbstractTransfer = $this->getProductAbstractById($idProductAbstract);

        if (!$productAbstractTransfer) {
            return $attributeProcessor;
        }

        $concreteProductCollection = $this->productConcreteManager->getConcreteProductsByAbstractProductId($idProductAbstract);

        return $this->attributeManager->buildAttributeProcessor($productAbstractTransfer, $concreteProductCollection);
    }

    /**
     * @param string $sku
     *
     * @throws \Spryker\Zed\Product\Business\Exception\MissingProductException
     *
     * @return string
     */
    public function getAbstractSkuFromProductConcrete($sku)
    {
        $productConcrete = $this->productQueryContainer
            ->queryProductConcreteBySku($sku)
            ->findOne();

        if (!$productConcrete) {
            throw new MissingProductException(
                sprintf(
                    'Tried to retrieve a product concrete with sku %s, but it does not exist.',
                    $sku
                )
            );
        }

        return $productConcrete->getSpyProductAbstract()->getSku();
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return \Orm\Zed\Product\Persistence\SpyProductAbstract
     */
    protected function persistEntity(ProductAbstractTransfer $productAbstractTransfer)
    {
        $jsonAttributes = $this->attributeManager->encodeAttributes(
            $productAbstractTransfer->getAttributes()
        );

        $productAbstractEntity = $this->productQueryContainer
            ->queryProductAbstract()
            ->filterByIdProductAbstract($productAbstractTransfer->getIdProductAbstract())
            ->findOneOrCreate();

        $productAbstractEntity
            ->setAttributes($jsonAttributes)
            ->setSku($productAbstractTransfer->getSku())
            ->setFkTaxSet($productAbstractTransfer->getTaxSetId());

        $productAbstractEntity->save();

        return $productAbstractEntity;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function persistPrice(ProductAbstractTransfer $productAbstractTransfer)
    {
        $priceTransfer = $productAbstractTransfer->getPrice();
        if ($priceTransfer instanceof PriceProductTransfer) {
            $priceTransfer->setIdProductAbstract(
                $productAbstractTransfer
                    ->requireIdProductAbstract()
                    ->getIdProductAbstract()
            );
            $this->priceFacade->persistAbstractProductPrice($priceTransfer);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer
     */
    protected function loadTaxSetId(ProductAbstractTransfer $productAbstractTransfer)
    {
        $taxSetEntity = $this->productQueryContainer
            ->queryTaxSetForProductAbstract($productAbstractTransfer->getIdProductAbstract())
            ->findOne();

        if ($taxSetEntity === null) {
            return $productAbstractTransfer;
        }

        $productAbstractTransfer->setTaxSetId($taxSetEntity->getIdTaxSet());

        return $productAbstractTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer
     */
    protected function loadLocalizedAttributes(ProductAbstractTransfer $productAbstractTransfer)
    {
        $productAttributeCollection = $this->productQueryContainer
            ->queryProductAbstractLocalizedAttributes($productAbstractTransfer->getIdProductAbstract())
            ->find();

        foreach ($productAttributeCollection as $attributeEntity) {
            $localeTransfer = $this->localeFacade->getLocaleById($attributeEntity->getFkLocale());

            $localizedAttributesTransfer = $this->attributeManager->createLocalizedAttributesTransfer(
                $attributeEntity->toArray(),
                $attributeEntity->getAttributes(),
                $localeTransfer
            );

            $productAbstractTransfer->addLocalizedAttributes($localizedAttributesTransfer);
        }

        return $productAbstractTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer
     */
    protected function loadPrice(ProductAbstractTransfer $productAbstractTransfer)
    {
        $priceTransfer = $this->priceFacade->getProductAbstractPrice(
            $productAbstractTransfer->getIdProductAbstract()
        );

        if ($priceTransfer === null) {
            return $productAbstractTransfer;
        }

        $productAbstractTransfer->setPrice($priceTransfer);

        return $productAbstractTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function triggerBeforeCreatePlugins(ProductAbstractTransfer $productAbstractTransfer)
    {
        foreach ($this->pluginsCreateCollection as $plugin) {
            $plugin->run($productAbstractTransfer);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function triggerAfterCreatePlugins(ProductAbstractTransfer $productAbstractTransfer)
    {
        foreach ($this->pluginsCreateCollection as $plugin) {
            $plugin->run($productAbstractTransfer);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function triggerBeforeUpdatePlugins(ProductAbstractTransfer $productAbstractTransfer)
    {
        foreach ($this->pluginsUpdateCollection as $plugin) {
            $plugin->run($productAbstractTransfer);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function triggerAfterUpdatePlugins(ProductAbstractTransfer $productAbstractTransfer)
    {
        foreach ($this->pluginsUpdateCollection as $plugin) {
            $plugin->run($productAbstractTransfer);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     *
     * @return void
     */
    protected function triggerLoadPlugins(ProductAbstractTransfer $productAbstractTransfer)
    {
        foreach ($this->pluginsReadCollection as $plugin) {
            $plugin->run($productAbstractTransfer);
        }
    }

}
