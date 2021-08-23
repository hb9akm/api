<?php
declare(strict_types=1);

namespace HB9AKM\API\V2\Model\Entity;

class Endpoint extends AbstractEntity {
    protected int $id;
    protected string $name;
    protected string $label;
    protected string $description;
    protected $locale;

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getLabel(): string {
        return $this->label;
    }

    public function setLabel(string $label): void {
        $this->label = $label;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function setTranslatableLocale(string $locale): void {
        $this->locale = $locale;
    }
}

