<?php

namespace Icinga\Module\X509\Web\Control\SearchBar;

use Exception;
use Icinga\Module\X509\Common\Database;
use ipl\Orm\Exception\InvalidColumnException;
use ipl\Orm\Model;
use ipl\Orm\Relation;
use ipl\Orm\Relation\HasOne;
use ipl\Orm\Resolver;
use ipl\Orm\UnionModel;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Seq;
use ipl\Stdlib\Str;
use ipl\Web\Control\SearchBar\SearchException;
use ipl\Web\Control\SearchBar\Suggestions;

class ObjectSuggestions extends Suggestions
{
    use Database;

    /** @var Model */
    protected $model;

    /**
     * Set the model to show suggestions for
     *
     * @param string|Model $model
     *
     * @return $this
     */
    public function setModel($model): self
    {
        if (is_string($model)) {
            $model = new $model();
        }

        $this->model = $model;

        return $this;
    }

    protected function shouldShowRelationFor(string $column): bool
    {
        $columns = Str::trimSplit($column, '.');

        switch (count($columns)) {
            case 2:
                return $columns[0] !== $this->model->getTableAlias();
            default:
                return true;
        }
    }

    protected function createQuickSearchFilter($searchTerm)
    {
        $model = $this->model;
        $resolver = $model::on($this->getDb())->getResolver();

        $quickFilter = Filter::any();
        foreach ($model->getSearchColumns() as $column) {
            $where = Filter::like($resolver->qualifyColumn($column, $model->getTableAlias()), $searchTerm);
            $where->metaData()->set('columnLabel', $resolver->getColumnDefinition($where->getColumn())->getLabel());
            $quickFilter->add($where);
        }

        return $quickFilter;
    }

    protected function fetchValueSuggestions($column, $searchTerm, Filter\Chain $searchFilter)
    {
        $model = $this->model;
        $query = $model::on($this->getDb());
        $query->limit(static::DEFAULT_LIMIT);

        if (strpos($column, ' ') !== false) {
            // Searching for `Host Name` and continue typing without accepting/clicking the suggested
            // column name will cause the search bar to use a label as a filter column
            list($path, $_) = Seq::find(
                self::collectFilterColumns($query->getModel(), $query->getResolver()),
                $column,
                false
            );
            if ($path !== null) {
                $column = $path;
            }
        }

        $columnPath = $query->getResolver()->qualifyPath($column, $model->getTableAlias());
        $inputFilter = Filter::like($columnPath, $searchTerm);

        $query->columns($columnPath);
        $query->orderBy($columnPath);

        if ($searchFilter instanceof Filter\None) {
            $query->filter($inputFilter);
        } elseif ($searchFilter instanceof Filter\All) {
            $searchFilter->add($inputFilter);

            // When 10 hosts are sharing the same certificate, filtering in the search bar by
            // `Host Name=foo&Host Name=` will suggest only `foo` for the second filter. So, we have
            // to force the filter processor to optimize search bar filter
            $searchFilter->metaData()->set('forceOptimization', true);
            $inputFilter->metaData()->set('forceOptimization', false);
        } else {
            $searchFilter = $inputFilter;
        }

        $query->filter($searchFilter);
        // Not to suggest something like Port=443,443,443....
        $query->getSelectBase()->distinct();

        try {
            $steps = Str::trimSplit($column, '.');
            $columnName = array_pop($steps);
            if ($steps[0] === $model->getTableAlias()) {
                array_shift($steps);
            }

            foreach ($query as $row) {
                $model = $row;
                foreach ($steps as $step) {
                    try {
                        $model = $model->$step;
                    } catch (Exception $_) {
                        // pass
                        break;
                    }
                }

                $value = $model->$columnName;
                if ($value && is_string($value) && ! ctype_print($value)) { // Is binary
                    $value = bin2hex($value);
                } elseif ($value === false || $value === true) {
                    // TODO: The search bar is never going to suggest boolean types, so this
                    //  is a hack to workaround this limitation!!
                    $value = $value ? 'y' : 'n';
                }

                yield $value;
            }
        } catch (InvalidColumnException $e) {
            throw new SearchException(sprintf(t('"%s" is not a valid column'), $e->getColumn()));
        }
    }

    protected function fetchColumnSuggestions($searchTerm)
    {
        $model = $this->model;
        $query = $model::on($this->getDb());

        yield from self::collectFilterColumns($model, $query->getResolver());
    }

    public static function collectFilterColumns(Model $model, Resolver $resolver)
    {
        if ($model instanceof UnionModel) {
            $models = [];
            foreach ($model->getUnions() as $union) {
                /** @var Model $unionModel */
                $unionModel = new $union[0]();
                $models[$unionModel->getTableAlias()] = $unionModel;
                self::collectRelations($resolver, $unionModel, $models, []);
            }
        } else {
            $models = [$model->getTableAlias() => $model];
            self::collectRelations($resolver, $model, $models, []);
        }

        foreach ($models as $path => $targetModel) {
            /** @var Model $targetModel */
            foreach ($resolver->getColumnDefinitions($targetModel) as $columnName => $definition) {
                yield "$path.$columnName" => $definition->getLabel();
            }
        }
    }

    protected static function collectRelations(Resolver $resolver, Model $subject, array &$models, array $path)
    {
        foreach ($resolver->getRelations($subject) as $name => $relation) {
            /** @var Relation $relation */
            $isHasOne = $relation instanceof HasOne;
            $relationPath = [$name];

            if (! isset($models[$name]) && ! in_array($name, $path, true)) {
                if ($isHasOne || empty($path)) {
                    array_unshift($relationPath, $subject->getTableAlias());
                }

                $relationPath = array_merge($path, $relationPath);
                $targetPath = implode('.', $relationPath);

                if (! isset($models[$targetPath])) {
                    $models[$targetPath] = $relation->getTarget();
                    self::collectRelations($resolver, $relation->getTarget(), $models, $relationPath);
                    return;
                }
            } else {
                $path = [];
            }
        }
    }
}
