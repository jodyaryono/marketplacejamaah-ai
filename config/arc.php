<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Arc Blockchain Configuration
    | Hackathon: Agentic Economy on Arc @ Lablab.ai 2026
    |--------------------------------------------------------------------------
    */

    // RPC endpoint for Arc blockchain
    'rpc_url' => env('ARC_RPC_URL', 'https://rpc.arc-testnet.io'),

    // Operator wallet (custodial, holds user funds on their behalf)
    'operator_address' => env('ARC_OPERATOR_ADDRESS', '0x0000000000000000000000000000000000000000'),
    'operator_pk'      => env('ARC_OPERATOR_PK', ''),

    // USYC token contract address on Arc
    'usyc_contract' => env('ARC_USYC_CONTRACT', '0x1234567890AbcDEF1234567890abcdef12345678'),

    // Platform fee wallet
    'fee_address' => env('ARC_FEE_ADDRESS', '0x0000000000000000000000000000000000000000'),

    // Use testnet (true = mock blockchain, false = real Arc L2)
    'testnet' => env('ARC_TESTNET', true),

    // Platform fee percentage (0.001 = 0.1%)
    'platform_fee' => env('ARC_PLATFORM_FEE', 0.001),

    // USYC yield APY (passed to frontend for display)
    'usyc_apy' => 0.05, // 5% APY

    // Python AI agent microservice URL
    'ai_agent_url' => env('ARC_AI_AGENT_URL', 'http://localhost:8001'),
];
