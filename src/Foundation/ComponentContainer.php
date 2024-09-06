<?php

namespace StackWeb\Foundation;

class ComponentContainer
{

    public function __construct(
        public readonly Component $component,
    )
    {
    }

    public array $__props = [];

    public array $__states = [];

    public function mount(array $props)
    {
        $this->__props = $props;
        $this->__states = [];
        foreach ($this->component->states as $name => [$default, ])
        {
            $this->__states[$name] = $default->call($this);
        }
    }

    public function __get(string $name)
    {
        if (array_key_exists($name, $this->__states))
        {
            return $this->__states[$name];
        }

        if (array_key_exists($name, $this->__props))
        {
            return $this->__props[$name];
        }

        // if (array_key_exists($name, $this->__states))
        // {
        //     return $this->__states[$name];
        // } // Todo: Slots

        return null;
    }

    public function __set(string $name, $value) : void
    {
        $this->__states[$name] = $value;
    }

}