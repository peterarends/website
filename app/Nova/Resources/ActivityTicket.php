<?php

namespace App\Nova\Resources;

use Advoor\NovaEditorJs\NovaEditorJs;
use App\Models\Activity as ActivityModel;
use App\Models\ActivityTicket as ActivityTicketModel;
use App\Nova\Fields\Price;
use App\Nova\Fields\Seats;
use App\Nova\Flexible\Presets\ActivityTicketForm;
use Benjaminhirsch\NovaSlugField\Slug;
use Benjaminhirsch\NovaSlugField\TextWithSlug;
use DanielDeWit\NovaPaperclip\PaperclipImage;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Whitecube\NovaFlexibleContent\Flexible;

/**
 * A ticket for an activity
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ActivityTicket extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = ActivityTicketModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * Name of the group
     *
     * @var string
     */
    public static $group = 'Activities';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'tagline',
        'description',
    ];

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Activity Tickets');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Activity Ticket');
    }

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string
     */
    public function subtitle()
    {
        if ($this->for_member && $this->for_guest) {
            return __('All users');
        }

        if ($this->for_member) {
            return __('Members only');
        }

        return __('Guests only');
    }


    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fields(Request $request)
    {
        return [
            // ID
            ID::make()->sortable(),

            // Dates
            DateTime::make('Created At')->onlyOnDetail(),
            DateTime::make('Updated At')->onlyOnDetail(),

            // Activity
            BelongsTo::make('Activity')
                ->required(),

            // Meta
            Text::make('Title', 'name')
                ->required()
                ->sortable()
                ->rules('required', 'between:4,255'),

            Text::make('Description', 'description')
                ->nullable()
                ->rules('nullable', 'between:4,255')
                ->hideFromIndex(),

            // Availability
            Text::make('Availability', function() {
                if ($this->for_member && $this->for_guest) {
                    return __('All users');
                }
                return __($this->for_member ? 'Members only' : 'Guests only');
            })->onlyOnIndex(),

            Boolean::make('For members', 'for_member')
                ->rules('required_without:for_guest')
                ->hideFromIndex(),

            Boolean::make('For guests', 'for_guest')
                ->rules('required_without:for_member')
                ->hideFromIndex(),

            HasMany::make('Ticket Payments', 'ticketPayments', ActivityTicketPayment::class),
        ];
    }

    /**
     * Return query that is filtered on allowed activities, IF the user is
     * not allowed to view them all
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private static function queryAllOrManaged(NovaRequest $request, $query)
    {
        // User is admin, don't filter
        if ($request->user()->can('admin', ActivityModel::class)) {
            return $query;
        }

        // User only has a subset of queries, filter it
        return $query->whereIn('activity_id', $request->user()->getHostedActivityQuery($query)->pluck('id'));
    }

    /**
     * Make sure the user can only see enrollments he/she is allowed to see
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Filter invisible nodes
        $query = self::queryAllOrManaged($request, $query);

        // Order by activity → member/guest → name
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];
            return $query
                ->orderByDesc('activity_id')
                ->orderByDesc('for_member')
                ->orderBy('name');
        }
    }

    /**
     * Build a "relatable" query for the given resource.
     *
     * This query determines which instances of the model may be attached to other resources.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function relatableQuery(NovaRequest $request, $query)
    {
        return self::queryAllOrManaged($request, parent::relatableQuery($request, $query));
    }

    /**
     * Build a Scout search query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Laravel\Scout\Builder  $query
     * @return \Laravel\Scout\Builder
     */
    public static function scoutQuery(NovaRequest $request, $query)
    {
        return self::queryAllOrManaged($request, parent::scoutQuery($request, $query));
    }
}
