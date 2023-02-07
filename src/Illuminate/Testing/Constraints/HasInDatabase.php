<?php

namespace Illuminate\Testing\Constraints;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Constraint\Constraint;

class HasInDatabase extends Constraint
{
    /**
     * Number of records that will be shown in the console in case of failure.
     *
     * @var int
     */
    protected $show = 3;

    /**
     * The database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $database;

    /**
     * The data that will be used to narrow the search in the database table.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new constraint instance.
     *
     * @param  \Illuminate\Database\Connection  $database
     * @param  array  $data
     * @return void
     */
    public function __construct(Connection $database, array $data)
    {
        $this->data = $data;

        $this->database = $database;
    }

    /**
     * Check if the data is found in the given table.
     *
     * @param  string  $table
     * @return bool
     */
    public function matches($table): bool
    {
        return $this->database->table($table)->where($this->data)->count() > 0;
    }

    /**
     * Get the description of the failure.
     *
     * @param  string  $table
     * @return string
     */
    public function failureDescription($table): string
    {
        return sprintf(
            "a row in the table [%s] matches the attributes %s.\n\n%s",
            $table, $this->toString(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $this->getAdditionalInfo($table)
        );
    }

    /**
     * Get additional info about the records found in the database table.
     *
     * @param  string  $table
     * @return string
     */
    protected function getAdditionalInfo($table)
    {
        $query = $this->database->table($table);

        $firstKey = array_key_first($this->data);
        $firstValue = $this->data[array_key_first($this->data)];

        if (is_numeric($firstKey) && is_array($firstValue)) {
            $similarResults = $query->where(...array_values($firstValue))
                ->select(Arr::pluck($this->data, 0))->limit($this->show)->get();
        } else {
            $similarResults = $query->where(
                array_key_first($this->data),
                $this->data[array_key_first($this->data)]
            )->select(array_keys($this->data))->limit($this->show)->get();
        }

        if ($similarResults->isNotEmpty()) {
            $description = 'Found similar results: '.json_encode($similarResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $query = $this->database->table($table);

            if (is_numeric($firstKey) && is_array($firstValue)) {
                $query->select(Arr::pluck($this->data, 0));
            } else {
                $query->select(array_keys($this->data));
            }
            $results = $query->limit($this->show)->get();

            if ($results->isEmpty()) {
                return 'The table is empty';
            }

            $description = 'Found: '.json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if ($query->count() > $this->show) {
            $description .= sprintf(' and %s others', $query->count() - $this->show);
        }

        return $description;
    }

    /**
     * Get a string representation of the object.
     *
     * @param  int  $options
     * @return string
     */
    public function toString($options = 0): string
    {
        foreach ($this->data as $key => $data) {
            if (is_numeric($key) && is_array($data)) {
                foreach ($data as [$key, /*$operator*/, $value]) {
                    $output[$key] = $value instanceof Expression ? (string) $value : $value;
                }
            } else {
                
                $output[$key] = $data instanceof Expression ? (string) $data : $data;
            }
        }
        dump(json_encode($output ?? [], $options));
        return json_encode($output ?? [], $options);
    }
}
