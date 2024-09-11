import * as PhpFunctions from './php-functions'
import {PHPRef} from "./php-types";

export class Scope {

    $static: string
    $this: Object
    vars: Object

    v: any

    constructor($static: string = undefined, $this: Object = undefined, vars: Object = {}) {
        this.$static = $static
        this.$this = $this
        this.vars = vars

        // @ts-ignore
        this.v = new Proxy<Scope>(this, {
            get(target: Scope, p: string | symbol, receiver: any): any {
                if (p == 'this') {
                    return target.$this
                }

                let value = target.vars[p]

                if (value instanceof PHPRef) {
                    return value.get()
                }

                return value
            },

            set(target: Scope, p: string | symbol, newValue: any, receiver: any): boolean {
                if (target.vars[p] instanceof PHPRef) {
                    target.vars[p].set(newValue)
                    return true
                }

                target.vars[p] = newValue
                return true
            },

            has(target: Scope, p: string | symbol): boolean {
                return target.vars[p] !== undefined
            },
        })
    }

    ref(name: string) {
        let value = this.vars[name]

        if (value instanceof PHPRef) {
            return value
        }

        return this.vars[name] = new PHPRef(value)
    }

}

export class Params {

    $this: Object
    $static: string

    values: Object
    i: number

    constructor(values: Object, $this: Object, $static: string) {
        this.$this = $this
        this.$static = $static
        this.values = values
        this.i = 0
    }

    next(name: string, defaults : () => any = undefined): any {
        if (this.values[name] !== undefined) {
            let value = this.values[name]
            delete this.values[name]

            return value
        }

        if (this.values[this.i] !== undefined) {
            let value = this.values[this.i++]
            delete this.values[name]

            return value
        }

        if (defaults) {
            return defaults()
        }

        throw new Error(`Parameter ${name} not passed`)
    }

    end() {
    }

}

export class PHPUtils {

    /**
     * Operator for +
     *
     * @param left
     * @param right
     */
    static opAdd(left: any, right: any) {
        return this.toNumber(left) + this.toNumber(right)
    }

    /**
     * Operator for -
     *
     * @param left
     * @param right
     */
    static opSub(left: any, right: any) {
        return this.toNumber(left) - this.toNumber(right)
    }

    /**
     * Operator for *
     *
     * @param left
     * @param right
     */
    static opMul(left: any, right: any) {
        return this.toNumber(left) * this.toNumber(right)
    }

    /**
     * Operator for /
     *
     * @param left
     * @param right
     */
    static opDiv(left: any, right: any) {
        return this.toNumber(left) / this.toNumber(right)
    }

    /**
     * Operator for .
     *
     * @param left
     * @param right
     */
    static opDot(left: any, right: any) {
        return this.toString(left) + this.toString(right)
    }

    static getType(value: any) : string {
        switch (typeof value) {
            case "undefined":
                return "null"

            case "number":
                return "double"

            case "bigint":
                return "integer"

            case "boolean":
                return "boolean"

            case "string":
                return "string"

            case "object":
                if (value === null) {
                    return "null"
                }

                if (value instanceof Array) {
                    return "array"
                }

                return "object"

            case "function":
            case "symbol":
            default:
                return "object"
        }
    }

    static toNumber(value: any) {
        let type = typeof value

        switch (type)
        {
            case "bigint":
            case "number":
                return value

            case "boolean":
                return value ? 1 : 0

            case "undefined":
                return 0

            case "object":
                return value === null ? 1 : 0

            case "string":
                return +value

            default:
                return 1
        }
    }

    static toString(value: any) {
        let type = typeof value

        switch (type)
        {
            case "bigint":
            case "number":
                return '' + value

            case "boolean":
                return value ? '1' : ''

            case "undefined":
                return ''

            case "object":
                return value === null ? '' : 'object'

            default:
                return 'object'
        }
    }

}

export let PHP = {
    functions: PhpFunctions,
}

// @ts-ignore
window.PHP = PHP
