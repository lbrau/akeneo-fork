<?php


namespace AkeneoTest\Pim\Enrichment\Integration\Fixture;

use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Saver\AttributeSaver;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Saver\FamilySaver;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Test\Common\EntityBuilder as AttributeBuilder;
use Akeneo\Test\Common\EntityBuilder as FamilyBuilder;
use Akeneo\Test\Common\EntityBuilder as FamilyVariantBuilder;
use Akeneo\Test\Common\EntityBuilder as ProductModelBuilder;
use Akeneo\Test\Common\EntityWithValue\Builder\Product as ProductBuilder;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Bundle\StorageUtilsBundle\Doctrine\Common\Remover\BaseRemover;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductAndProductModelWithRemovedAttributeLoader
{
    private array $productIdentifierToUuidMapping = [];

    public function __construct(
        private ProductBuilder $productBuilder,
        private SaverInterface $productSaver,
        private ProductModelBuilder $productModelBuilder,
        private SaverInterface $productModelSaver,
        private FamilyVariantBuilder $familyVariantBuilder,
        private SaverInterface $familyVariantSaver,
        private FamilyBuilder $familyBuilder,
        private FamilySaver $familySaver,
        private AttributeBuilder $attributeBuilder,
        private AttributeSaver $attributeSaver,
        private AttributeRepository $attributeRepository,
        private RemoverInterface $attributeRemover,
        private ValidatorInterface $entityValidator,
        private Client $productAndProductModelEsClient
    ) {
    }

    public function load(): void
    {
        $this->createAttribute([
            'code' => 'an_attribute',
            'type' => 'pim_catalog_text',
            'group' => 'other',
        ]);

        $this->createAttribute([
            'code' => 'a_second_attribute',
            'type' => 'pim_catalog_text',
            'group' => 'other',
        ]);

        $this->createAttribute([
            'code' => 'a_third_attribute',
            'type' => 'pim_catalog_text',
            'group' => 'other',
        ]);

        $this->createAttribute([
            'code' => 'a_forth_attribute',
            'type' => 'pim_catalog_boolean',
            'group' => 'other',
        ]);

        $this->createAttribute([
            'code' => 'a_fifth_attribute',
            'type' => 'pim_catalog_boolean',
            'group' => 'other',
        ]);

        $this->createFamily([
            'code' => 'a_family',
            'attributes' => [
                'sku',
                'an_attribute',
                'a_second_attribute',
                'a_forth_attribute',
                'a_fifth_attribute',
            ],
        ]);

        $this->createFamily([
            'code' => 'a_second_family',
            'attributes' => [
                'sku',
                'a_second_attribute',
                'a_third_attribute',
            ],
        ]);

        $this->createFamilyVariant([
            'code' => 'a_family_variant',
            'variant_attribute_sets' => [
                [
                    'axes' => ['a_forth_attribute'],
                    'attributes' => [],
                    'level' => 1,
                ],
            ],
            'family' => 'a_family',
        ]);

        $this->createFamilyVariant([
            'code' => 'a_second_family_variant',
            'variant_attribute_sets' => [
                [
                    'axes' => ['a_forth_attribute'],
                    'attributes' => ['an_attribute'],
                    'level' => 1,
                ],
                [
                    'axes' => ['a_fifth_attribute'],
                    'attributes' => ['a_second_attribute'],
                    'level' => 2,
                ],
            ],
            'family' => 'a_family',
        ]);

        // Simple product
        $this->productIdentifierToUuidMapping['product_1'] = $this->createProduct([
            'identifier' => 'product_1',
            'family' => 'a_family',
            'values' => [
                'an_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_second_attribute' => [
                    [
                        'data' => 'bar',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ])->getUuid()->toString();

        // Simple product
        $this->productIdentifierToUuidMapping['product_2'] = $this->createProduct([
            'identifier' => 'product_2',
            'family' => 'a_second_family',
            'values' => [
                'a_second_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_third_attribute' => [
                    [
                        'data' => 'foobar',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ])->getUuid()->toString();

        // Simple product
        $this->productIdentifierToUuidMapping['product_4'] = $this->createProduct([
            'identifier' => 'product_4',
            'family' => 'a_second_family',
            'values' => [
                'a_second_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_third_attribute' => [
                    [
                        'data' => 'barfoo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ])->getUuid()->toString();


        // Simple product
        $this->productIdentifierToUuidMapping['product_5'] = $this->createProduct([
            'identifier' => 'product_5',
            'family' => 'a_family',
            'values' => [],
        ])->getUuid()->toString();

        // Simple product with no identifier
        $this->productIdentifierToUuidMapping['product_6'] = $this->createProduct([
            'identifier' => null,
            'family' => 'a_family',
            'values' => [
                'a_third_attribute' => [
                    [
                        'data' => 'super value',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ])->getUuid()->toString();

        // Product model with only one level of variations
        $this->createProductModel([
            'code' => 'a_product_model',
            'family_variant' => 'a_family_variant',
            'values' => [
                'an_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_second_attribute' => [
                    [
                        'data' => 'bar',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ]);

        // Product model with only two level of variations
        $this->createProductModel([
            'code' => 'a_second_product_model',
            'family_variant' => 'a_second_family_variant',
            'values' => [],
        ]);

        //Sub product model for the second level of variations
        $this->createProductModel([
            'code' => 'a_sub_product_model',
            'parent' => 'a_second_product_model',
            'values' => [
                'an_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_forth_attribute' => [
                    [
                        'data' => true,
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ]);

        //Sub product model for the second level of variations
        $this->createProductModel([
            'code' => 'a_second_sub_product_model',
            'parent' => 'a_second_product_model',
            'values' => [
                'a_forth_attribute' => [
                    [
                        'data' => false,
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ]);

        // Variant product for the two level of variations
        $this->productIdentifierToUuidMapping['product_3'] = $this->createProduct([
            'identifier' => 'product_3',
            'family' => 'a_family',
            'parent' => 'a_sub_product_model',
            'values' => [
                'a_second_attribute' => [
                    [
                        'data' => 'foo',
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
                'a_fifth_attribute' => [
                    [
                        'data' => true,
                        'locale' => null,
                        'scope' => null,
                    ],
                ],
            ],
        ])->getUuid()->toString();

        $this->removeAttribute('an_attribute');
        $this->removeAttribute('a_third_attribute');
    }

    public function getproductIdentifierToUuidMapping(): array
    {
        return $this->productIdentifierToUuidMapping;
    }

    private function createProduct(array $data = []): ProductInterface
    {
        $this->productBuilder
            ->init()
            ->withIdentifier($data['identifier'] ?? 'new_product_' . uniqid())
            ->withFamily($data['family'] ?? 'a_family')
        ;

        if (!empty($data['values'])) {
            foreach ($data["values"] as $attribute => $value) {
                foreach ($value as $valueByChannelAnLocale) {
                    $this->productBuilder->withValue(
                        $attribute,
                        array_key_exists('data', $valueByChannelAnLocale) ? $valueByChannelAnLocale['data'] : '',
                        $valueByChannelAnLocale['locale'] ?? '',
                        $valueByChannelAnLocale['channel'] ?? ''
                    );
                }
            }
        }

        if (!empty($data['parent'])) {
            $this->productBuilder->withParent($data['parent']);
        }

        $product = $this->productBuilder->build();

        $this->productSaver->save($product);
        $this->productAndProductModelEsClient->refreshIndex();

        return $product;
    }

    private function createProductModel(array $data = []): ProductModelInterface
    {
        $productModel = $this->productModelBuilder->build($data, true);
        $this->productModelSaver->save($productModel);
        $this->productAndProductModelEsClient->refreshIndex();

        return $productModel;
    }

    private function createFamilyVariant(array $data = []): FamilyVariantInterface
    {
        $familyVariant = $this->familyVariantBuilder->build($data, true);
        $this->familyVariantSaver->save($familyVariant);

        return $familyVariant;
    }

    private function createFamily(array $data = []): FamilyInterface
    {
        $family = $this->familyBuilder->build($data, true);
        $this->familySaver->save($family);

        return $family;
    }

    private function createAttribute(array $data = []): AttributeInterface
    {
        $attribute = $this->attributeBuilder->build($data, true);
        $this->attributeSaver->save($attribute);

        return $attribute;
    }

    private function removeAttribute(string $attributeCode)
    {
        $attribute = $this->attributeRepository->findOneBy(['code' => $attributeCode]);

        if (null == $attribute) {
            throw new \LogicException(sprintf('Attribute %s not found', $attributeCode));
        }

        $this->attributeRemover->remove($attribute);
    }
}
