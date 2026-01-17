export type SotadexSolana = {
  version: "0.1.0";
  name: "sotadex_solana";
  instructions: [
    {
      name: "init";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "admin";
          isMut: false;
          isSigner: false;
        },
        {
          name: "dexOperator";
          isMut: false;
          isSigner: false;
        },
        {
          name: "feeCollector";
          isMut: false;
          isSigner: false;
        },
        {
          name: "insurance";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sotadexTokenAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "tokenId";
          isMut: false;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        },
        {
          name: "tokenProgram";
          isMut: false;
          isSigner: false;
        },
        {
          name: "rent";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "id";
          type: "u64";
        },
        {
          name: "bumps";
          type: {
            defined: "SotadexBumps";
          };
        }
      ];
    },
    {
      name: "initMember";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "memberPubkey";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexAccount";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "bump";
          type: "u8";
        }
      ];
    },
    {
      name: "initPosition";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "memberPubkey";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexAccount";
          isMut: false;
          isSigner: false;
        },
        {
          name: "positionAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "instrumentId";
          type: "u16";
        },
        {
          name: "bump";
          type: "u8";
        }
      ];
    },
    {
      name: "deposit";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "senderTokenAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sotadexAccount";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexTokenAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sotadexMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        },
        {
          name: "tokenProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "amount";
          type: "u64";
        }
      ];
    },
    {
      name: "withdraw";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "userTokenAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sotadexAccount";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexTokenAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sotadexMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "feeCollectorMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        },
        {
          name: "tokenProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "withdrawArg";
          type: {
            defined: "WithdrawArg";
          };
        }
      ];
    },
    {
      name: "funding";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "sotadexAccount";
          isMut: false;
          isSigner: false;
        },
        {
          name: "sotadexMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "fundingArg";
          type: {
            defined: "FundingArg";
          };
        }
      ];
    },
    {
      name: "trade";
      accounts: [
        {
          name: "sender";
          isMut: true;
          isSigner: true;
        },
        {
          name: "sotadexAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "buyerMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "buyerPositionAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sellerMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "sellerPositionAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "feeCollectorMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "insuranceMemberAccount";
          isMut: true;
          isSigner: false;
        },
        {
          name: "systemProgram";
          isMut: false;
          isSigner: false;
        }
      ];
      args: [
        {
          name: "fillArg";
          type: {
            defined: "FillArg";
          };
        }
      ];
    }
  ];
  accounts: [
    {
      name: "userAccount";
      type: {
        kind: "struct";
        fields: [
          {
            name: "margin";
            type: "u64";
          }
        ];
      };
    },
    {
      name: "sotadexAccount";
      type: {
        kind: "struct";
        fields: [
          {
            name: "id";
            type: "u64";
          },
          {
            name: "tokenId";
            type: "publicKey";
          },
          {
            name: "admin";
            type: "publicKey";
          },
          {
            name: "dexOperator";
            type: "publicKey";
          },
          {
            name: "feeCollector";
            type: "publicKey";
          },
          {
            name: "insurance";
            type: "publicKey";
          },
          {
            name: "bumps";
            type: {
              defined: "SotadexBumps";
            };
          },
          {
            name: "tradeWindow";
            type: {
              vec: "u64";
            };
          },
          {
            name: "fundingWindow";
            type: {
              vec: "u64";
            };
          },
          {
            name: "withdrawWindow";
            type: {
              vec: "u64";
            };
          }
        ];
      };
    },
    {
      name: "memberAccount";
      type: {
        kind: "struct";
        fields: [
          {
            name: "user";
            type: "publicKey";
          },
          {
            name: "dexAccount";
            type: "publicKey";
          },
          {
            name: "margin";
            type: "i64";
          },
          {
            name: "bump";
            type: "u8";
          }
        ];
      };
    },
    {
      name: "positionAccount";
      type: {
        kind: "struct";
        fields: [
          {
            name: "user";
            type: "publicKey";
          },
          {
            name: "dexAccount";
            type: "publicKey";
          },
          {
            name: "instrumentId";
            type: "u16";
          },
          {
            name: "quantity";
            type: "i64";
          },
          {
            name: "bump";
            type: "u8";
          }
        ];
      };
    }
  ];
  types: [
    {
      name: "FundingArg";
      type: {
        kind: "struct";
        fields: [
          {
            name: "id";
            type: "u64";
          },
          {
            name: "operationId";
            type: "u64";
          },
          {
            name: "user";
            type: "publicKey";
          },
          {
            name: "amount";
            type: "i64";
          }
        ];
      };
    },
    {
      name: "SotadexBumps";
      type: {
        kind: "struct";
        fields: [
          {
            name: "sotadexAccount";
            type: "u8";
          },
          {
            name: "sotadexTokenAccount";
            type: "u8";
          }
        ];
      };
    },
    {
      name: "FillArg";
      type: {
        kind: "struct";
        fields: [
          {
            name: "id";
            type: "u64";
          },
          {
            name: "operationId";
            type: "u64";
          },
          {
            name: "buyer";
            type: "publicKey";
          },
          {
            name: "seller";
            type: "publicKey";
          },
          {
            name: "quantity";
            type: "u64";
          },
          {
            name: "price";
            type: "u64";
          },
          {
            name: "bankruptPrice";
            type: "u64";
          },
          {
            name: "bankruptFee";
            type: "u64";
          },
          {
            name: "liquidationSide";
            type: "u8";
          },
          {
            name: "buyerFee";
            type: "u64";
          },
          {
            name: "sellerFee";
            type: "u64";
          },
          {
            name: "instrumentId";
            type: "u16";
          }
        ];
      };
    },
    {
      name: "WithdrawArg";
      type: {
        kind: "struct";
        fields: [
          {
            name: "id";
            type: "u64";
          },
          {
            name: "operationId";
            type: "u64";
          },
          {
            name: "user";
            type: "publicKey";
          },
          {
            name: "amount";
            type: "u64";
          },
          {
            name: "fee";
            type: "u64";
          }
        ];
      };
    },
    {
      name: "LiquidationSide";
      type: {
        kind: "enum";
        variants: [
          {
            name: "NONE";
          },
          {
            name: "BUY";
          },
          {
            name: "SELL";
          }
        ];
      };
    },
    {
      name: "ActionType";
      type: {
        kind: "enum";
        variants: [
          {
            name: "MatchingBuy";
          },
          {
            name: "MatchingSell";
          },
          {
            name: "Funding";
          },
          {
            name: "Withdraw";
          }
        ];
      };
    },
    {
      name: "LiquidationSide";
      type: {
        kind: "enum";
        variants: [
          {
            name: "NONE";
          },
          {
            name: "BUY";
          },
          {
            name: "SELL";
          }
        ];
      };
    },
    {
      name: "TradeErrorCode";
      type: {
        kind: "enum";
        variants: [
          {
            name: "TradeAlreadyExist";
          }
        ];
      };
    }
  ];
  events: [
    {
      name: "DepositEvent";
      fields: [
        {
          name: "user";
          type: "publicKey";
          index: true;
        },
        {
          name: "sotadexAccount";
          type: "publicKey";
          index: false;
        },
        {
          name: "usdcAmount";
          type: "u64";
          index: false;
        },
        {
          name: "timestamp";
          type: "i64";
          index: false;
        }
      ];
    },
    {
      name: "WithdrawEvent";
      fields: [
        {
          name: "user";
          type: "publicKey";
          index: true;
        },
        {
          name: "id";
          type: "u64";
          index: false;
        },
        {
          name: "operationId";
          type: "u64";
          index: false;
        },
        {
          name: "sotadexAccount";
          type: "publicKey";
          index: false;
        },
        {
          name: "usdcAmount";
          type: "u64";
          index: false;
        },
        {
          name: "fee";
          type: "u64";
          index: false;
        },
        {
          name: "timestamp";
          type: "i64";
          index: false;
        }
      ];
    },
    {
      name: "FundingEvent";
      fields: [
        {
          name: "user";
          type: "publicKey";
          index: true;
        },
        {
          name: "id";
          type: "u64";
          index: false;
        },
        {
          name: "operationId";
          type: "u64";
          index: false;
        },
        {
          name: "sotadexAccount";
          type: "publicKey";
          index: false;
        },
        {
          name: "amount";
          type: "i64";
          index: false;
        },
        {
          name: "timestamp";
          type: "i64";
          index: false;
        }
      ];
    },
    {
      name: "TradeEvent";
      fields: [
        {
          name: "id";
          type: "u64";
          index: false;
        },
        {
          name: "operationId";
          type: "u64";
          index: false;
        },
        {
          name: "instrumentId";
          type: "u16";
          index: false;
        },
        {
          name: "buyer";
          type: "publicKey";
          index: false;
        },
        {
          name: "seller";
          type: "publicKey";
          index: false;
        },
        {
          name: "fillQuantity";
          type: "u64";
          index: false;
        },
        {
          name: "fillPrice";
          type: "u64";
          index: false;
        },
        {
          name: "bankruptPrice";
          type: "u64";
          index: false;
        },
        {
          name: "liquidationSide";
          type: "u8";
          index: false;
        },
        {
          name: "buyerMargin";
          type: "i64";
          index: false;
        },
        {
          name: "buyerPosition";
          type: "i64";
          index: false;
        },
        {
          name: "sellerMargin";
          type: "i64";
          index: false;
        },
        {
          name: "sellerPosition";
          type: "i64";
          index: false;
        },
        {
          name: "sotadexAccount";
          type: "publicKey";
          index: false;
        },
        {
          name: "timestamp";
          type: "i64";
          index: false;
        }
      ];
    },
    {
      name: "UpdateMarginEvent";
      fields: [
        {
          name: "actionId";
          type: "u64";
          index: true;
        },
        {
          name: "actionType";
          type: "u8";
          index: false;
        },
        {
          name: "operationId";
          type: "u64";
          index: false;
        },
        {
          name: "user";
          type: "publicKey";
          index: false;
        },
        {
          name: "oldMargin";
          type: "i64";
          index: false;
        },
        {
          name: "newMargin";
          type: "i64";
          index: false;
        },
        {
          name: "sotadexAccount";
          type: "publicKey";
          index: false;
        },
        {
          name: "timestamp";
          type: "i64";
          index: false;
        }
      ];
    }
  ];
  errors: [
    {
      code: 6000;
      name: "InvalidSuperAdmin";
      msg: "Invalid super admin";
    },
    {
      code: 6001;
      name: "InvalidDexOperator";
      msg: "Invalid dex operator";
    },
    {
      code: 6002;
      name: "DuplicateTradeId";
      msg: "Duplicate trade id";
    },
    {
      code: 6003;
      name: "InvalidInitParam";
      msg: "Invalid Init Param";
    }
  ];
};

export const IDL: SotadexSolana = {
  version: "0.1.0",
  name: "sotadex_solana",
  instructions: [
    {
      name: "init",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "admin",
          isMut: false,
          isSigner: false,
        },
        {
          name: "dexOperator",
          isMut: false,
          isSigner: false,
        },
        {
          name: "feeCollector",
          isMut: false,
          isSigner: false,
        },
        {
          name: "insurance",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sotadexTokenAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "tokenId",
          isMut: false,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
        {
          name: "tokenProgram",
          isMut: false,
          isSigner: false,
        },
        {
          name: "rent",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "id",
          type: "u64",
        },
        {
          name: "bumps",
          type: {
            defined: "SotadexBumps",
          },
        },
      ],
    },
    {
      name: "initMember",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "memberPubkey",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexAccount",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "bump",
          type: "u8",
        },
      ],
    },
    {
      name: "initPosition",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "memberPubkey",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexAccount",
          isMut: false,
          isSigner: false,
        },
        {
          name: "positionAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "instrumentId",
          type: "u16",
        },
        {
          name: "bump",
          type: "u8",
        },
      ],
    },
    {
      name: "deposit",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "senderTokenAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sotadexAccount",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexTokenAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sotadexMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
        {
          name: "tokenProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "amount",
          type: "u64",
        },
      ],
    },
    {
      name: "withdraw",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "userTokenAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sotadexAccount",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexTokenAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sotadexMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "feeCollectorMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
        {
          name: "tokenProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "withdrawArg",
          type: {
            defined: "WithdrawArg",
          },
        },
      ],
    },
    {
      name: "funding",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "sotadexAccount",
          isMut: false,
          isSigner: false,
        },
        {
          name: "sotadexMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "fundingArg",
          type: {
            defined: "FundingArg",
          },
        },
      ],
    },
    {
      name: "trade",
      accounts: [
        {
          name: "sender",
          isMut: true,
          isSigner: true,
        },
        {
          name: "sotadexAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "buyerMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "buyerPositionAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sellerMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "sellerPositionAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "feeCollectorMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "insuranceMemberAccount",
          isMut: true,
          isSigner: false,
        },
        {
          name: "systemProgram",
          isMut: false,
          isSigner: false,
        },
      ],
      args: [
        {
          name: "fillArg",
          type: {
            defined: "FillArg",
          },
        },
      ],
    },
  ],
  accounts: [
    {
      name: "userAccount",
      type: {
        kind: "struct",
        fields: [
          {
            name: "margin",
            type: "u64",
          },
        ],
      },
    },
    {
      name: "sotadexAccount",
      type: {
        kind: "struct",
        fields: [
          {
            name: "id",
            type: "u64",
          },
          {
            name: "tokenId",
            type: "publicKey",
          },
          {
            name: "admin",
            type: "publicKey",
          },
          {
            name: "dexOperator",
            type: "publicKey",
          },
          {
            name: "feeCollector",
            type: "publicKey",
          },
          {
            name: "insurance",
            type: "publicKey",
          },
          {
            name: "bumps",
            type: {
              defined: "SotadexBumps",
            },
          },
          {
            name: "tradeWindow",
            type: {
              vec: "u64",
            },
          },
          {
            name: "fundingWindow",
            type: {
              vec: "u64",
            },
          },
          {
            name: "withdrawWindow",
            type: {
              vec: "u64",
            },
          },
        ],
      },
    },
    {
      name: "memberAccount",
      type: {
        kind: "struct",
        fields: [
          {
            name: "user",
            type: "publicKey",
          },
          {
            name: "dexAccount",
            type: "publicKey",
          },
          {
            name: "margin",
            type: "i64",
          },
          {
            name: "bump",
            type: "u8",
          },
        ],
      },
    },
    {
      name: "positionAccount",
      type: {
        kind: "struct",
        fields: [
          {
            name: "user",
            type: "publicKey",
          },
          {
            name: "dexAccount",
            type: "publicKey",
          },
          {
            name: "instrumentId",
            type: "u16",
          },
          {
            name: "quantity",
            type: "i64",
          },
          {
            name: "bump",
            type: "u8",
          },
        ],
      },
    },
  ],
  types: [
    {
      name: "FundingArg",
      type: {
        kind: "struct",
        fields: [
          {
            name: "id",
            type: "u64",
          },
          {
            name: "operationId",
            type: "u64",
          },
          {
            name: "user",
            type: "publicKey",
          },
          {
            name: "amount",
            type: "i64",
          },
        ],
      },
    },
    {
      name: "SotadexBumps",
      type: {
        kind: "struct",
        fields: [
          {
            name: "sotadexAccount",
            type: "u8",
          },
          {
            name: "sotadexTokenAccount",
            type: "u8",
          },
        ],
      },
    },
    {
      name: "FillArg",
      type: {
        kind: "struct",
        fields: [
          {
            name: "id",
            type: "u64",
          },
          {
            name: "operationId",
            type: "u64",
          },
          {
            name: "buyer",
            type: "publicKey",
          },
          {
            name: "seller",
            type: "publicKey",
          },
          {
            name: "quantity",
            type: "u64",
          },
          {
            name: "price",
            type: "u64",
          },
          {
            name: "bankruptPrice",
            type: "u64",
          },
          {
            name: "bankruptFee",
            type: "u64",
          },
          {
            name: "liquidationSide",
            type: "u8",
          },
          {
            name: "buyerFee",
            type: "u64",
          },
          {
            name: "sellerFee",
            type: "u64",
          },
          {
            name: "instrumentId",
            type: "u16",
          },
        ],
      },
    },
    {
      name: "WithdrawArg",
      type: {
        kind: "struct",
        fields: [
          {
            name: "id",
            type: "u64",
          },
          {
            name: "operationId",
            type: "u64",
          },
          {
            name: "user",
            type: "publicKey",
          },
          {
            name: "amount",
            type: "u64",
          },
          {
            name: "fee",
            type: "u64",
          },
        ],
      },
    },
    {
      name: "LiquidationSide",
      type: {
        kind: "enum",
        variants: [
          {
            name: "NONE",
          },
          {
            name: "BUY",
          },
          {
            name: "SELL",
          },
        ],
      },
    },
    {
      name: "ActionType",
      type: {
        kind: "enum",
        variants: [
          {
            name: "MatchingBuy",
          },
          {
            name: "MatchingSell",
          },
          {
            name: "Funding",
          },
          {
            name: "Withdraw",
          },
        ],
      },
    },
    {
      name: "LiquidationSide",
      type: {
        kind: "enum",
        variants: [
          {
            name: "NONE",
          },
          {
            name: "BUY",
          },
          {
            name: "SELL",
          },
        ],
      },
    },
    {
      name: "TradeErrorCode",
      type: {
        kind: "enum",
        variants: [
          {
            name: "TradeAlreadyExist",
          },
        ],
      },
    },
  ],
  events: [
    {
      name: "DepositEvent",
      fields: [
        {
          name: "user",
          type: "publicKey",
          index: true,
        },
        {
          name: "sotadexAccount",
          type: "publicKey",
          index: false,
        },
        {
          name: "usdcAmount",
          type: "u64",
          index: false,
        },
        {
          name: "timestamp",
          type: "i64",
          index: false,
        },
      ],
    },
    {
      name: "WithdrawEvent",
      fields: [
        {
          name: "user",
          type: "publicKey",
          index: true,
        },
        {
          name: "id",
          type: "u64",
          index: false,
        },
        {
          name: "operationId",
          type: "u64",
          index: false,
        },
        {
          name: "sotadexAccount",
          type: "publicKey",
          index: false,
        },
        {
          name: "usdcAmount",
          type: "u64",
          index: false,
        },
        {
          name: "fee",
          type: "u64",
          index: false,
        },
        {
          name: "timestamp",
          type: "i64",
          index: false,
        },
      ],
    },
    {
      name: "FundingEvent",
      fields: [
        {
          name: "user",
          type: "publicKey",
          index: true,
        },
        {
          name: "id",
          type: "u64",
          index: false,
        },
        {
          name: "operationId",
          type: "u64",
          index: false,
        },
        {
          name: "sotadexAccount",
          type: "publicKey",
          index: false,
        },
        {
          name: "amount",
          type: "i64",
          index: false,
        },
        {
          name: "timestamp",
          type: "i64",
          index: false,
        },
      ],
    },
    {
      name: "TradeEvent",
      fields: [
        {
          name: "id",
          type: "u64",
          index: false,
        },
        {
          name: "operationId",
          type: "u64",
          index: false,
        },
        {
          name: "instrumentId",
          type: "u16",
          index: false,
        },
        {
          name: "buyer",
          type: "publicKey",
          index: false,
        },
        {
          name: "seller",
          type: "publicKey",
          index: false,
        },
        {
          name: "fillQuantity",
          type: "u64",
          index: false,
        },
        {
          name: "fillPrice",
          type: "u64",
          index: false,
        },
        {
          name: "bankruptPrice",
          type: "u64",
          index: false,
        },
        {
          name: "liquidationSide",
          type: "u8",
          index: false,
        },
        {
          name: "buyerMargin",
          type: "i64",
          index: false,
        },
        {
          name: "buyerPosition",
          type: "i64",
          index: false,
        },
        {
          name: "sellerMargin",
          type: "i64",
          index: false,
        },
        {
          name: "sellerPosition",
          type: "i64",
          index: false,
        },
        {
          name: "sotadexAccount",
          type: "publicKey",
          index: false,
        },
        {
          name: "timestamp",
          type: "i64",
          index: false,
        },
      ],
    },
    {
      name: "UpdateMarginEvent",
      fields: [
        {
          name: "actionId",
          type: "u64",
          index: true,
        },
        {
          name: "actionType",
          type: "u8",
          index: false,
        },
        {
          name: "operationId",
          type: "u64",
          index: false,
        },
        {
          name: "user",
          type: "publicKey",
          index: false,
        },
        {
          name: "oldMargin",
          type: "i64",
          index: false,
        },
        {
          name: "newMargin",
          type: "i64",
          index: false,
        },
        {
          name: "sotadexAccount",
          type: "publicKey",
          index: false,
        },
        {
          name: "timestamp",
          type: "i64",
          index: false,
        },
      ],
    },
  ],
  errors: [
    {
      code: 6000,
      name: "InvalidSuperAdmin",
      msg: "Invalid super admin",
    },
    {
      code: 6001,
      name: "InvalidDexOperator",
      msg: "Invalid dex operator",
    },
    {
      code: 6002,
      name: "DuplicateTradeId",
      msg: "Duplicate trade id",
    },
    {
      code: 6003,
      name: "InvalidInitParam",
      msg: "Invalid Init Param",
    },
  ],
};
