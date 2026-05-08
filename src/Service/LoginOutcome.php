<?php

namespace App\Service;

final class LoginOutcome
{
    public const ACTION_NONE = 'none';
    public const ACTION_REDIRECT = 'redirect';
    public const ACTION_RENDER = 'render';

    private string $action;
    private ?string $redirectRoute = null;
    private ?string $template = null;
    
    /** @var array<int> templateParams */
    private array $templateParams = [];
    private ?string $flashType = null;
    private ?string $flashMessage = null;

    private function __construct(string $action)
    {
        $this->action = $action;
    }

    public static function none(?string $flashType = null, ?string $flashMessage = null): self
    {
        $outcome = new self(self::ACTION_NONE);
        $outcome->flashType = $flashType;
        $outcome->flashMessage = $flashMessage;

        return $outcome;
    }

    public static function redirect(string $route, ?string $flashType = null, ?string $flashMessage = null): self
    {
        $outcome = new self(self::ACTION_REDIRECT);
        $outcome->redirectRoute = $route;
        $outcome->flashType = $flashType;
        $outcome->flashMessage = $flashMessage;

        return $outcome;
    }

    public static function render(string $template, array $params, ?string $flashType = null, ?string $flashMessage = null): self
    {
        $outcome = new self(self::ACTION_RENDER);
        $outcome->template = $template;
        $outcome->templateParams = $params;
        $outcome->flashType = $flashType;
        $outcome->flashMessage = $flashMessage;

        return $outcome;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getRedirectRoute(): ?string
    {
        return $this->redirectRoute;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getTemplateParams(): array
    {
        return $this->templateParams;
    }

    public function getFlashType(): ?string
    {
        return $this->flashType;
    }

    public function getFlashMessage(): ?string
    {
        return $this->flashMessage;
    }
}
