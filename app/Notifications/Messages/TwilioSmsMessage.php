<?php

namespace App\Notifications\Messages;

class TwilioSmsMessage
{
    /**
     * The message content
     */
    protected string $content = '';

    /**
     * The sender phone number
     */
    protected ?string $from = null;

    /**
     * The recipient phone number
     */
    protected ?string $to = null;

    /**
     * Media URLs to include
     */
    protected array $mediaUrls = [];

    /**
     * Whether to send as MMS
     */
    protected bool $isMms = false;

    /**
     * Create a new SMS message instance
     */
    public static function create(?string $content = null): self
    {
        $message = new static();

        if ($content) {
            $message->content($content);
        }

        return $message;
    }

    /**
     * Set the message content
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set the sender phone number
     */
    public function from(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    /**
     * Set the recipient phone number
     */
    public function to(string $to): self
    {
        $this->to = $to;
        return $this;
    }

    /**
     * Add a media URL to the message
     */
    public function media(string $url): self
    {
        $this->mediaUrls[] = $url;
        $this->isMms = true;
        return $this;
    }

    /**
     * Add multiple media URLs
     */
    public function mediaUrls(array $urls): self
    {
        foreach ($urls as $url) {
            $this->media($url);
        }
        return $this;
    }

    /**
     * Set whether this is an MMS message
     */
    public function mms(bool $isMms = true): self
    {
        $this->isMms = $isMms;
        return $this;
    }

    /**
     * Get the message content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the sender phone number
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Get the recipient phone number
     */
    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * Get the media URLs
     */
    public function getMediaUrls(): array
    {
        return $this->mediaUrls;
    }

    /**
     * Check if this is an MMS message
     */
    public function isMms(): bool
    {
        return $this->isMms || !empty($this->mediaUrls);
    }

    /**
     * Convert the message to an array for API calls
     */
    public function toArray(): array
    {
        $data = [
            'Body' => $this->content,
        ];

        if ($this->from) {
            $data['From'] = $this->from;
        }

        if ($this->to) {
            $data['To'] = $this->to;
        }

        if ($this->isMms() && !empty($this->mediaUrls)) {
            $data['MediaUrl'] = $this->mediaUrls;
        }

        return $data;
    }

    /**
     * Convert the message to a string
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Magic method for fluent interface
     */
    public function __call(string $method, array $parameters): self
    {
        if (method_exists($this, $method)) {
            $result = $this->$method(...$parameters);
            return $result instanceof self ? $result : $this;
        }

        // Allow setting arbitrary properties
        if (count($parameters) === 1) {
            $this->$method = $parameters[0];
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
