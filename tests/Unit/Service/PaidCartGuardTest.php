<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace {
    if (!class_exists('Cart')) {
        class Cart
        {
            /** @var int */
            public $id = 0;
            /** @var array<int, array{id_cart_rule:int}> */
            public $cartRules = [];
            /** @var int[] */
            public $addedRules = [];
            /** @var array|false */
            public $duplication = false;

            public function duplicate()
            {
                return $this->duplication;
            }

            public function getCartRules()
            {
                return $this->cartRules;
            }

            public function addCartRule($id)
            {
                $this->addedRules[] = (int) $id;

                return true;
            }
        }
    }
}

namespace Buckaroo\PrestaShop\Tests\Unit\Service {
    use Buckaroo\PrestaShop\Src\Service\PaidCartGuard;
    use PHPUnit\Framework\TestCase;

    /**
     * The cart being paid stays out of the session, so changes in another tab never
     * reach the order created after the payment (#337 review).
     */
    final class PaidCartGuardTest extends TestCase
    {
        /** @var \Context */
        private $context;

        protected function setUp(): void
        {
            $this->context = new \Context();
            $this->context->cookie = new class {
                /** @var int */
                public $id_cart = 0;
                /** @var int */
                public $writes = 0;

                public function __set($name, $value): void
                {
                    $this->{$name} = $value;
                }

                public function write(): void
                {
                    ++$this->writes;
                }
            };
        }

        public function testTheSessionContinuesWithACopyThatKeepsTheVouchers(): void
        {
            $paid = new \Cart();
            $paid->id = 34;
            $paid->cartRules = [['id_cart_rule' => 5], ['id_cart_rule' => 9]];
            $copy = new \Cart();
            $copy->id = 35;
            $paid->duplication = ['cart' => $copy, 'success' => true];

            $result = (new PaidCartGuard($this->context))->moveSessionToCopy($paid);

            self::assertSame($copy, $result);
            self::assertSame([5, 9], $copy->addedRules);
            self::assertSame(35, $this->context->cookie->id_cart);
            self::assertSame(1, $this->context->cookie->writes);
            self::assertSame([], $paid->addedRules, 'the paid cart itself is not changed');
        }

        public function testTheSessionKeepsThePaidCartWhenItCannotBeCopied(): void
        {
            $paid = new \Cart();
            $paid->id = 34;
            $this->context->cookie->id_cart = 34;

            foreach ([false, ['cart' => new \Cart(), 'success' => false], ['cart' => null, 'success' => true]] as $duplication) {
                $paid->duplication = $duplication;

                self::assertNull((new PaidCartGuard($this->context))->moveSessionToCopy($paid));
                self::assertSame(34, $this->context->cookie->id_cart);
                self::assertSame(0, $this->context->cookie->writes);
            }
        }
    }
}
