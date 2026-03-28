<?php declare(strict_types=1);

namespace Sharp\Compiler\Lexer;

enum TokenType: string
{
    case TEXT              = 'TEXT';
    case ECHO_OPEN         = 'ECHO_OPEN';       // {{
    case ECHO_CLOSE        = 'ECHO_CLOSE';      // }}
    case RAW_ECHO_OPEN     = 'RAW_ECHO_OPEN';   // {!!
    case RAW_ECHO_CLOSE    = 'RAW_ECHO_CLOSE';  // !!}
    case COMMENT           = 'COMMENT';          // <!-- ... -->
    case DIRECTIVE         = 'DIRECTIVE';        // #word
    case DIRECTIVE_ARGS    = 'DIRECTIVE_ARGS';   // (...) content after directive
    case COMPONENT_OPEN    = 'COMPONENT_OPEN';   // <UserCard or <user-card
    case COMPONENT_CLOSE   = 'COMPONENT_CLOSE';  // </UserCard> or </user-card>
    case COMPONENT_SELF_CLOSE = 'COMPONENT_SELF_CLOSE'; // />
    case SLOT_OPEN         = 'SLOT_OPEN';        // <slot
    case SLOT_CLOSE        = 'SLOT_CLOSE';       // </slot>
    case ATTR_NAME         = 'ATTR_NAME';        // class  or  :user  (dynamic prefix)
    case ATTR_VALUE        = 'ATTR_VALUE';       // "..."
    case EXPR              = 'EXPR';             // expression inside {{ }} or {!! !!}
    case EOF               = 'EOF';
}
