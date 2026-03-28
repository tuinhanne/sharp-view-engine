<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast;

enum NodeType: string
{
    case ROOT      = 'ROOT';
    case TEXT      = 'TEXT';
    case ECHO      = 'ECHO';
    case RAW_ECHO  = 'RAW_ECHO';
    case COMMENT   = 'COMMENT';
    case IF        = 'IF';
    case FOREACH   = 'FOREACH';
    case WHILE     = 'WHILE';
    case EXTENDS   = 'EXTENDS';
    case SECTION   = 'SECTION';
    case YIELD     = 'YIELD';
    case PARENT    = 'PARENT';
    case INCLUDE   = 'INCLUDE';
    case COMPONENT = 'COMPONENT';
    case SLOT      = 'SLOT';
    case DIRECTIVE = 'DIRECTIVE';
    case BREAK     = 'BREAK';
    case CONTINUE  = 'CONTINUE';
    case SET       = 'SET';
    case PUSH          = 'PUSH';
    case PREPEND       = 'PREPEND';
    case STACK         = 'STACK';
    case SWITCH        = 'SWITCH';
    case FOR           = 'FOR';
    case DUMP          = 'DUMP';
    case DD            = 'DD';
    case PHP           = 'PHP';
    case INCLUDE_WHEN  = 'INCLUDE_WHEN';
    case INCLUDE_FIRST = 'INCLUDE_FIRST';
    case INCLUDE_IF    = 'INCLUDE_IF';
    case PROPS         = 'PROPS';
}
