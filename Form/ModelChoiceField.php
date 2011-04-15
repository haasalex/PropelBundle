<?php

namespace Propel\PropelBundle\Form;

use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\InvalidOptionsException;
use Symfony\Component\Form\ChoiceField;

/**
 * A field for selecting one or more from a list of Propel objects.
 *
 * <code>
 * $form->add(new ModelChoiceField('tags', array(
 *     'class' => 'Application\Model\Tag',
 * )));
 * </code>
 *
 * Additionally to the options in ChoiceField, the following options are
 * available:
 *
 *  * class:          The class of the selectable objects. Required.
 *  * property:       The property displayed as value of the choices. If this
 *                    option is not available, the field will try to convert
 *                    objects into strings using __toString().
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelChoiceField extends ChoiceField
{
    /**
     * The objects from which the user can choose
     *
     * This array is either indexed by ID (if the ID is a single field)
     * or by key in the choices array (if the ID consists of multiple fields)
     *
     * This property is initialized by initializeChoices(). It should only
     * be accessed through getModel() and getEntities().
     *
     * @var Collection
     */
    protected $objects = null;

    /**
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through getIdentifierFields().
     *
     * @var array
     */
    protected $identifier = array();

    /**
     * A cache for \ReflectionProperty instances for the underlying class
     *
     * This property should only be accessed through getReflProperty().
     *
     * @var array
     */
    protected $reflProperties = array();

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('class');
        $this->addOption('property');

        // Override option - it is not required for this subclass
        $this->addOption('choices', array());

        parent::configure();

        // The objects can be passed directly in the "choices" option.
        // In this case, initializing the model cache is a cheap operation
        // so do it now!
        if (is_array($this->getOption('choices')) && count($this->getOption('choices')) > 0) {
            $this->initializeChoices();
        }
     }

    /**
     * Initializes the choices and returns them
     *
     * The choices are generated from the objects. If the objects have a
     * composite identifier, the choices are indexed using ascending integers.
     * Otherwise the identifiers are used as indices.
     *
     * If the objects were passed in the "choices" option, this method
     * does not have any significant overhead.
     *
     * If the option "property" was passed, the property path in that option
     * is used as option values. Otherwise this method tries to convert
     * objects to strings using __toString().
     *
     * @return array  An array of choices
     */
    protected function getInitializedChoices()
    {
        if ($this->getOption('choices')) {
            $objects = parent::getInitializedChoices();
        } else {
            $queryClass = $this->getOption('class').'Query';
            $objects = $queryClass::create()->find();
        }

        $propertyPath = null;
        $choices = array();
        $this->objects = array();

        // The propery option defines, which property (path) is used for
        // displaying objects as strings
        if ($this->getOption('property')) {
            $propertyPath = new PropertyPath($this->getOption('property'));
        }

        foreach ($objects as $key => $model) {
            if ($propertyPath) {
                // If the property option was given, use it
                $value = $propertyPath->getValue($model);
            } else {
                // Otherwise expect a __toString() method in the model
                $value = (string)$model;
            }

            if (count($this->getIdentifierFields()) > 1) {
                // When the identifier consists of multiple field, use
                // naturally ordered keys to refer to the choices
                $choices[$key] = $value;
                $this->objects[$key] = $model;
            } else {
                // When the identifier is a single field, index choices by
                // model ID for performance reasons
                $id = current($this->getIdentifierValues($model));
                $choices[$id] = $value;
                $this->objects[$id] = $model;
            }
        }

        return $choices;
    }

    /**
     * Returns the according objects for the choices
     *
     * If the choices were not initialized, they are initialized now. This
     * is an expensive operation, except if the objects were passed in the
     * "choices" option.
     *
     * @return array  An array of objects
     */
    protected function getEntities()
    {
        if (!$this->objects) {
            // indirectly initializes the objects property
            $this->initializeChoices();
        }

        return $this->objects;
    }

    /**
     * Returns the model for the given key
     *
     * If the underlying objects have composite identifiers, the choices
     * are initialized. The key is expected to be the index in the choices
     * array in this case.
     *
     * If they have single identifiers, they are either fetched from the
     * internal model cache (if filled) or loaded from the database.
     *
     * @param  string $key  The choice key (for objects with composite
     *                      identifiers) or model ID (for objects with single
     *                      identifiers)
     * @return object       The matching model
     */
    protected function getModel($key)
    {
        $id = $this->getIdentifierFields();

        if (count($id) > 1) {
            // $key is a collection index
            $objects = $this->getEntities();
            return $objects[$key];
        } else if ($this->objects) {
            return $this->objects[$key];
        }

        $queryClass = $this->getOption('class').'Query';

        return $queryClass::create()->findOneById($key);

    }

    /**
     * Returns the \ReflectionProperty instance for a property of the
     * underlying class
     *
     * @param  string $property     The name of the property
     * @return \ReflectionProperty  The reflection instance
     */
    protected function getReflProperty($property)
    {
        if (!isset($this->reflProperties[$property])) {
            $this->reflProperties[$property] = new \ReflectionProperty($this->getOption('class'), $property);
            $this->reflProperties[$property]->setAccessible(true);
        }

        return $this->reflProperties[$property];
    }

    /**
     * Returns the fields included in the identifier of the underlying class
     *
     * @return array  An array of field names
     */
    protected function getIdentifierFields()
    {
        if (!$this->identifier) {
            $this->identifier = array('id');
        }

        return $this->identifier;
    }

    /**
     * Returns the values of the identifier fields of an model
     *
     * @param  object $model  The model for which to get the identifier
     * @throws FormException   If the model does not exist in Propel's
     *                         idmodel map
     */
    protected function getIdentifierValues($model)
    {
        return array('id');
    }

    /**
     * Merges the selected and deselected objects into the collection passed
     * when calling setData()
     *
     * @see parent::processData()
     */
    protected function processData($data)
    {
        // reuse the existing collection to optimize for Propel
        if ($data instanceof Collection) {
            $currentData = $this->getData();

            if (!$currentData) {
                $currentData = $data;
            } else if (count($data) === 0) {
                $currentData->clear();
            } else {
                // merge $data into $currentData
                foreach ($currentData as $model) {
                    if (!$data->contains($model)) {
                        $currentData->removeElement($model);
                    } else {
                        $data->removeElement($model);
                    }
                }

                foreach ($data as $model) {
                    $currentData->add($model);
                }
            }

            return $currentData;
        }

        return $data;
    }

    /**
     * Transforms choice keys into objects
     *
     * @param  mixed $keyOrKeys   An array of keys, a single key or NULL
     * @return Collection|object  A collection of objects, a single model
     *                            or NULL
     */
    protected function reverseTransform($keyOrKeys)
    {
        $keyOrKeys = parent::reverseTransform($keyOrKeys);

        if (null === $keyOrKeys) {
            return $this->getOption('multiple') ? new ArrayCollection() : null;
        }

        $notFound = array();

        if (count($this->getIdentifierFields()) > 1) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->getEntities()));
        } else if ($this->objects) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->objects));
        }

        if (0 === count($notFound)) {
            if (is_array($keyOrKeys)) {
                $result = new ArrayCollection();

                // optimize this into a SELECT WHERE IN query
                foreach ($keyOrKeys as $key) {
                    try {
                        $result->add($this->getModel($key));
                    } catch (NoResultException $e) {
                        $notFound[] = $key;
                    }
                }
            } else {
                try {
                    $result = $this->getModel($keyOrKeys);
                } catch (NoResultException $e) {
                    $notFound[] = $keyOrKeys;
                }
            }
        }

        if (count($notFound) > 0) {
            throw new TransformationFailedException('The objects with keys "%s" could not be found', implode('", "', $notFound));
        }

        return $result;
    }

    /**
     * Transforms objects into choice keys
     *
     * @param  Collection|object  A collection of objects, a single model or
     *                            NULL
     * @return mixed              An array of choice keys, a single key or
     *                            NULL
     */
    protected function transform($collectionOrModel)
    {
        if (null === $collectionOrModel) {
            return $this->getOption('multiple') ? array() : '';
        }

        if (count($this->identifier) > 1) {
            // load all choices
            $availableEntities = $this->getEntities();

            if ($collectionOrModel instanceof Collection) {
                $result = array();

                foreach ($collectionOrModel as $model) {
                    // identify choices by their collection key
                    $key = array_search($model, $availableEntities);
                    $result[] = $key;
                }
            } else {
                $result = array_search($collectionOrModel, $availableEntities);
            }
        } else {
            if ($collectionOrModel instanceof Collection) {
                $result = array();

                foreach ($collectionOrModel as $model) {
                    $result[] = current($this->getIdentifierValues($model));
                }
            } else {
                $result = current($this->getIdentifierValues($collectionOrModel));
            }
        }


        return parent::transform($result);
    }
}
