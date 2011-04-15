<?php

namespace Propel\PropelBundle\Form;

use Symfony\Component\Form\FieldFactory\FieldFactoryGuesserInterface;
use Symfony\Component\Form\FieldFactory\FieldFactoryClassGuess;
use Symfony\Component\Form\FieldFactory\FieldFactoryGuess;

/**
 * Guesses form fields for Propel.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelFieldFactoryGuesser implements FieldFactoryGuesserInterface
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function guessClass($class, $property)
    {
        $queryClass = $class.'Query';
        $query      = new $queryClass;
        $tableMap   = $query->getTableMap();

        $relationName = ucfirst($property);
        if ($tableMap->hasRelation($relationName)) {
            $relation = $tableMap->getRelation($relationName);
            $multiple = $relation->getType() == \RelationMap::ONE_TO_ONE ? false : true;

            return new FieldFactoryClassGuess(
                'Propel\PropelBundle\Form\ModelChoiceField',
                array(
                    'class' => $relation->getLocalTable()->getClassname(),
                    'multiple' => $multiple,
                ),
                FieldFactoryGuess::HIGH_CONFIDENCE
            );
        } else {
            switch (strtolower($tableMap->getColumn($property)->getType()))
            {
                //            case 'array':
                //                return new FieldFactoryClassGuess(
                //                    'Symfony\Component\Form\CollectionField',
                //                    array(),
                //                    FieldFactoryGuess::HIGH_CONFIDENCE
                //                );
            case 'boolean':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\CheckboxField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'datetime':
            case 'vardatetime':
            case 'datetimez':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\DateTimeField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'date':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\DateField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'decimal':
            case 'float':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\NumberField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                );
            case 'integer':
            case 'bigint':
            case 'smallint':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\IntegerField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                );
            case 'varchar':
            case 'string':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                );
            case 'text':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextareaField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                );
            case 'time':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TimeField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
                //                case 'object': ???
            }
        }

        return new FieldFactoryClassGuess(
            'Symfony\Component\Form\TextField',
            array(),
            FieldFactoryGuess::LOW_CONFIDENCE
        );
    }

    /**
     * @inheritDoc
     */
    public function guessRequired($class, $property)
    {
        $queryClass = $class.'Query';
        $query      = new $queryClass;
        $tableMap   = $query->getTableMap();

        $relationName = ucfirst($property);
        if (! $tableMap->hasRelation($relationName)) {
            if ($col = $tableMap->getColumn($property)) {
                if (! $col->isNotNull()) {
                    return new FieldFactoryGuess(
                        true,
                        FieldFactoryGuess::HIGH_CONFIDENCE
                    );

                    return new FieldFactoryGuess(
                        false,
                        FieldFactoryGuess::MEDIUM_CONFIDENCE
                    );
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function guessMaxLength($class, $property)
    {
        $queryClass = $class.'Query';
        $query      = new $queryClass;
        $tableMap   = $query->getTableMap();

        $relationName = ucfirst($property);
        if (! $tableMap->hasRelation($relationName)) {
            if ($col = $tableMap->getColumn($property)) {
                return new FieldFactoryGuess(
                    $col->getSize(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            }
        }

        return null;
    }
}
