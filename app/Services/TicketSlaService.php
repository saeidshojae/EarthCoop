<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\Carbon;

class TicketSlaService
{
    /**
     * SLA deadlines based on priority (in hours)
     */
    private const SLA_DEADLINES = [
        'high' => 4,      // 4 hours for high priority
        'normal' => 24,   // 24 hours for normal priority
        'low' => 72,      // 72 hours for low priority
    ];

    /**
     * First response SLA (in hours)
     */
    private const FIRST_RESPONSE_SLA = [
        'high' => 1,      // 1 hour for high priority
        'normal' => 4,    // 4 hours for normal priority
        'low' => 12,      // 12 hours for low priority
    ];

    /**
     * Calculate SLA deadline for a ticket
     */
    public function calculateDeadline(Ticket $ticket): ?Carbon
    {
        $priority = $ticket->priority ?? 'normal';
        $hours = self::SLA_DEADLINES[$priority] ?? self::SLA_DEADLINES['normal'];
        
        return $ticket->created_at->addHours($hours);
    }

    /**
     * Calculate first response deadline
     */
    public function calculateFirstResponseDeadline(Ticket $ticket): ?Carbon
    {
        $priority = $ticket->priority ?? 'normal';
        $hours = self::FIRST_RESPONSE_SLA[$priority] ?? self::FIRST_RESPONSE_SLA['normal'];
        
        return $ticket->created_at->addHours($hours);
    }

    /**
     * Check if ticket meets first response SLA
     */
    public function meetsFirstResponseSla(Ticket $ticket): bool
    {
        if (!$ticket->first_response_at) {
            return false;
        }

        $deadline = $this->calculateFirstResponseDeadline($ticket);
        return $ticket->first_response_at <= $deadline;
    }

    /**
     * Check if ticket meets resolution SLA
     */
    public function meetsResolutionSla(Ticket $ticket): bool
    {
        if (!$ticket->resolved_at || !$ticket->sla_deadline) {
            return false;
        }

        return $ticket->resolved_at <= $ticket->sla_deadline;
    }

    /**
     * Get remaining hours until deadline
     */
    public function getRemainingHours(Ticket $ticket): ?float
    {
        if (!$ticket->sla_deadline) {
            return null;
        }

        return max(0, now()->diffInHours($ticket->sla_deadline, false));
    }

    /**
     * Get SLA status
     */
    public function getSlaStatus(Ticket $ticket): string
    {
        if ($ticket->status === 'closed') {
            return $this->meetsResolutionSla($ticket) ? 'met' : 'missed';
        }

        if (!$ticket->sla_deadline) {
            return 'no_sla';
        }

        if ($ticket->isOverdue()) {
            return 'overdue';
        }

        if ($ticket->isApproachingDeadline(24)) {
            return 'warning';
        }

        return 'on_time';
    }

    /**
     * Get SLA performance statistics
     */
    public function getPerformanceStats($fromDate = null, $toDate = null): array
    {
        $query = Ticket::where('status', 'closed');

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $totalTickets = $query->count();
        $metFirstResponseSla = 0;
        $metResolutionSla = 0;
        $overdueTickets = 0;
        $averageFirstResponseTime = 0;
        $averageResolutionTime = 0;

        if ($totalTickets > 0) {
            $tickets = $query->get();
            $firstResponseTimes = [];
            $resolutionTimes = [];

            foreach ($tickets as $ticket) {
                if ($this->meetsFirstResponseSla($ticket)) {
                    $metFirstResponseSla++;
                }

                if ($this->meetsResolutionSla($ticket)) {
                    $metResolutionSla++;
                }

                if ($ticket->first_response_at && $ticket->created_at) {
                    $firstResponseTimes[] = $ticket->created_at->diffInMinutes($ticket->first_response_at);
                }

                if ($ticket->resolved_at && $ticket->created_at) {
                    $resolutionTimes[] = $ticket->created_at->diffInMinutes($ticket->resolved_at);
                }
            }

            $overdueTickets = $query->get()->filter(function($ticket) {
                return $this->getSlaStatus($ticket) === 'missed';
            })->count();

            if (count($firstResponseTimes) > 0) {
                $averageFirstResponseTime = round(array_sum($firstResponseTimes) / count($firstResponseTimes) / 60, 2); // in hours
            }

            if (count($resolutionTimes) > 0) {
                $averageResolutionTime = round(array_sum($resolutionTimes) / count($resolutionTimes) / 60, 2); // in hours
            }
        }

        return [
            'total_tickets' => $totalTickets,
            'met_first_response_sla' => $metFirstResponseSla,
            'met_resolution_sla' => $metResolutionSla,
            'missed_sla' => $overdueTickets,
            'first_response_sla_percentage' => $totalTickets > 0 ? round(($metFirstResponseSla / $totalTickets) * 100, 2) : 0,
            'resolution_sla_percentage' => $totalTickets > 0 ? round(($metResolutionSla / $totalTickets) * 100, 2) : 0,
            'average_first_response_time' => $averageFirstResponseTime,
            'average_resolution_time' => $averageResolutionTime,
        ];
    }

    /**
     * Get SLA performance by priority
     */
    public function getPerformanceByPriority($fromDate = null, $toDate = null): array
    {
        $priorities = ['high', 'normal', 'low'];
        $stats = [];

        foreach ($priorities as $priority) {
            $query = Ticket::where('status', 'closed')
                          ->where('priority', $priority);

            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->where('created_at', '<=', $toDate);
            }

            $tickets = $query->get();
            $total = $tickets->count();
            $metSla = $tickets->filter(function($ticket) {
                return $this->meetsResolutionSla($ticket);
            })->count();

            $stats[$priority] = [
                'total' => $total,
                'met_sla' => $metSla,
                'percentage' => $total > 0 ? round(($metSla / $total) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    /**
     * Get SLA trends over time
     */
    public function getTrends($months = 12): array
    {
        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $fromDate = $date->copy()->startOfMonth();
            $toDate = $date->copy()->endOfMonth();

            $monthStats = $this->getPerformanceStats($fromDate, $toDate);

            $trends[] = [
                'month' => \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m'),
                'total' => $monthStats['total_tickets'],
                'met_sla' => $monthStats['met_resolution_sla'],
                'percentage' => $monthStats['resolution_sla_percentage'],
            ];
        }

        return $trends;
    }
}

