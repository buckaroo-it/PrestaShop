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

namespace Buckaroo\PrestaShop\Src\Entity;

use Buckaroo\Models\Model;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @ORM\Entity()
 */
class BkGiftcards extends Model
{
    /**
     * @var int
     *
     * @ORM\Id
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string")
     */
    protected $code;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string")
     */
    protected $name;

    /**
     * @var string | null
     *
     * @ORM\Column(name="logo", type="string" nullable=true)
     */
    protected ?string $logo;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $created_at;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     */
    protected $updated_at;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTime $createdAt = null): void
    {
        $this->created_at = $createdAt ?? (new \DateTime('now'));
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTime $updatedAt = null): void
    {
        $this->updated_at = $updatedAt ?? (new \DateTime('now'));
    }
    public function toSqlData(string ...$exclude):array
    {
        $data = [];

        $data['id'] = $this->id ?? null;
        $data['name'] = isset($this->name) ? pSQL($this->name) : null;
        $data['code'] = isset($this->code) ? pSQL($this->code) : null;
        $data['logo'] = isset($this->logo) ? pSQL($this->logo) : null;
        $data['created_at'] = isset($this->created_at) ? $this->created_at->format('Y-m-d H:i:s') : null;
        $data['updated_at'] = isset($this->updated_at) ? $this->updated_at->format('Y-m-d H:i:s') : null;

        foreach ($exclude as $column) {
            unset($data[$column]);
        }
        return $data;
    }
}
