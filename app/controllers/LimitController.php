<?php

use Firefly\Storage\Budget\BudgetRepositoryInterface as BRI;
use Firefly\Storage\Limit\LimitRepositoryInterface as LRI;

/**
 * Class LimitController
 */
class LimitController extends BaseController
{

    protected $_budgets;
    protected $_limits;

    /**
     * @param BRI $budgets
     * @param LRI $limits
     */
    public function __construct(BRI $budgets, LRI $limits)
    {
        $this->_budgets = $budgets;
        $this->_limits = $limits;
    }

    /**
     * @param Budget $budget
     *
     * @return $this
     */
    public function create(\Budget $budget = null)
    {
        $periods = \Config::get('firefly.periods_to_text');
        $prefilled = [
            'startdate'   => Input::get('startdate') ? : date('Y-m-d'),
            'repeat_freq' => Input::get('repeat_freq') ? : 'monthly',
            'budget_id'   => $budget ? $budget->id : null
        ];

        $budgets = $this->_budgets->getAsSelectList();

        return View::make('limits.create')->with('budgets', $budgets)->with(
            'periods', $periods
        )->with('prefilled', $prefilled);
    }

    /**
     * @param Limit $limit
     *
     * @return $this
     */
    public function delete(\Limit $limit)
    {
        return View::make('limits.delete')->with('limit', $limit);
    }

    /**
     * @param Limit $limit
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(\Limit $limit)
    {
        $success = $this->_limits->destroy($limit);

        if ($success) {
            Session::flash('success', 'The envelope was deleted.');
        } else {
            Session::flash('error', 'Could not delete the envelope. Check the logs to be sure.');
        }
        if (Input::get('from') == 'date') {
            return Redirect::route('budgets.index');
        } else {
            return Redirect::route('budgets.index.budget');
        }
    }

    /**
     * @param Limit $limit
     *
     * @return $this
     */
    public function edit(Limit $limit)
    {
        $budgets = $this->_budgets->getAsSelectList();
        $periods = \Config::get('firefly.periods_to_text');

        return View::make('limits.edit')->with('limit', $limit)->with('budgets', $budgets)->with(
            'periods', $periods
        );
    }

    /**
     * @param Budget $budget
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function store(Budget $budget = null)
    {

        // find a limit with these properties, as we might already have one:
        $limit = $this->_limits->store(Input::all());
        if ($limit->validate()) {
            Session::flash('success', 'Envelope created!');
            if (Input::get('from') == 'date') {
                return Redirect::route('budgets.index');
            } else {
                return Redirect::route('budgets.index.budget');
            }
        } else {
            Session::flash('success', 'Could not save new envelope.');
            $budgetId = $budget ? $budget->id : null;
            $parameters = [$budgetId, 'from' => Input::get('from')];

            return Redirect::route('budgets.limits.create', $parameters)->withInput()
                ->withErrors($limit->errors());
        }
    }

    /**
     * @param Limit $limit
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function update(\Limit $limit)
    {
        // TODO move logic to repository.
        /** @var \Limit $limit */
        $limit->startdate = new \Carbon\Carbon(Input::get('date'));
        $limit->repeat_freq = Input::get('period');
        $limit->repeats = !is_null(Input::get('repeats')) && Input::get('repeats') == '1' ? 1 : 0;
        $limit->amount = floatval(Input::get('amount'));
        if ($limit->save()) {
            Session::flash('success', 'Limit saved!');
            foreach ($limit->limitrepetitions()->get() as $rep) {
                $rep->delete();
            }
            if (Input::get('from') == 'date') {
                return Redirect::route('budgets.index');
            } else {
                return Redirect::route('budgets.index.budget');
            }


        } else {
            Session::flash('error', 'Could not save new limit: ' . $limit->errors()->first());

            return Redirect::route('budgets.limits.edit', [$limit->id, 'from' => Input::get('from')])->withInput()
                ->withErrors($limit->errors());
        }

    }


} 