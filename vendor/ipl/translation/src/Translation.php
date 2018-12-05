<?php

namespace ipl\Translation;

/**
 * Trait for classes which require translation
 */
trait Translation
{
    /**
     * Translator
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Set the translator
     *
     * @param   TranslatorInterface $translator
     *
     * @return  $this
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * Translate a message
     *
     * @param   string  $message
     *
     * @return  string
     */
    public function translate($message)
    {
        if ($this->translator === null) {
            return $message;
        }

        return $this->translator->translate($message);
    }

    /**
     * Translate a plural message
     *
     * @param   string  $singular
     * @param   string  $plural
     * @param   int     $number
     *
     * @return  string
     */
    public function translatePlural($singular, $plural, $number)
    {
        if ($this->translator === null) {
            if ((int) $number !== 1) {
                return $plural;
            }

            return $singular;
        }

        return $this->translator->translatePlural($singular, $plural, $number);
    }
}
